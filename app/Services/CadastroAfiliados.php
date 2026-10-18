<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\OrigemCadastroAfiliado;
use App\Enums\SituacaoAfiliado;
use App\Enums\TipoGanhoAfiliado;
use App\Mail\AfiliadoAprovado;
use App\Mail\AfiliadoCadastroRecebido;
use App\Mail\AfiliadoRecusado;
use App\Models\Afiliados;
use App\Models\Usuarios;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cadastro do afiliado, feito de forma atômica: afiliado, chave Pix e regra de comissão inicial;
 * aprovação e recusa do cadastro público, com os e-mails (spec 012, US1 e US2).
 */
class CadastroAfiliados
{
    /**
     * Campos do Pix, gravados no meio de pagamento do afiliado.
     */
    private const CAMPOS_PIX = ['pix_tipo_chave', 'pix_chave'];

    /**
     * Campos da regra de comissão, gravados pelo RegraComissaoAfiliados.
     */
    private const CAMPOS_REGRA = ['tipo_ganho', 'percentual'];

    public function __construct(private RegraComissaoAfiliados $regras) {}

    /**
     * Cadastro pela gestão do painel: nasce Ativo; o Gerente fica como responsável (FR-008, FR-009).
     *
     * @param  array<string, mixed>  $dados  dados validados
     */
    public function cadastrar_pelo_painel(array $dados, Usuarios $autor): Afiliados
    {
        $dados['usuarios_id'] = $autor->funcao() === Funcao::Gerente ? $autor->id : ($dados['usuarios_id'] ?? null);

        return $this->criar($dados, SituacaoAfiliado::Ativo, OrigemCadastroAfiliado::Painel, $autor);
    }

    /**
     * Cadastro público: nasce Em análise, sem gerente e com a regra padrão (FR-011); e-mail de
     * cadastro recebido.
     *
     * @param  array<string, mixed>  $dados  dados validados, sem situação, gerente nem regra
     */
    public function cadastrar_publico(array $dados): Afiliados
    {
        $afiliado = $this->criar(
            Arr::except($dados, ['usuarios_id', ...self::CAMPOS_REGRA]),
            SituacaoAfiliado::EmAnálise,
            OrigemCadastroAfiliado::Público,
            null,
        );

        $this->enviar_email($afiliado, new AfiliadoCadastroRecebido($afiliado));

        return $afiliado;
    }

    /**
     * Aprova o cadastro em análise ou recusado (reavaliação); gerente e regra são opcionais.
     *
     * @param  array{usuarios_id?: ?int, tipo_ganho?: ?string, percentual?: mixed}  $opcoes
     */
    public function aprovar(Afiliados $afiliado, Usuarios $autor, array $opcoes = []): Afiliados
    {
        $afiliado = DB::transaction(function () use ($afiliado, $autor, $opcoes) {
            $afiliado = $this->bloquear_em_situacao($afiliado, [SituacaoAfiliado::EmAnálise, SituacaoAfiliado::Recusado], 'Só é possível aprovar um cadastro em análise ou recusado.');

            if (array_key_exists('usuarios_id', $opcoes)) {
                $afiliado->usuarios_id = $opcoes['usuarios_id'];
            }

            $afiliado->forceFill([
                'situacao' => SituacaoAfiliado::Ativo,
                'motivo_recusa' => null,
                'decidido_por' => $autor->id,
                'decidido_em' => now(),
            ])->save();

            if (isset($opcoes['tipo_ganho'], $opcoes['percentual'])) {
                $this->regras->trocar($afiliado, TipoGanhoAfiliado::from($opcoes['tipo_ganho']), (string) $opcoes['percentual'], $autor);
            }

            return $afiliado;
        });

        $this->enviar_email($afiliado, new AfiliadoAprovado($afiliado));

        return $afiliado;
    }

    /**
     * Recusa o cadastro em análise, com motivo opcional.
     */
    public function recusar(Afiliados $afiliado, Usuarios $autor, ?string $motivo): Afiliados
    {
        $afiliado = DB::transaction(function () use ($afiliado, $autor, $motivo) {
            $afiliado = $this->bloquear_em_situacao($afiliado, [SituacaoAfiliado::EmAnálise], 'Só é possível recusar um cadastro em análise.');

            $afiliado->forceFill([
                'situacao' => SituacaoAfiliado::Recusado,
                'motivo_recusa' => $motivo,
                'decidido_por' => $autor->id,
                'decidido_em' => now(),
            ])->save();

            return $afiliado;
        });

        $this->enviar_email($afiliado, new AfiliadoRecusado($afiliado));

        return $afiliado;
    }

    /**
     * Bloqueia o afiliado e confere a situação, para duas decisões simultâneas não se cruzarem.
     *
     * @param  list<SituacaoAfiliado>  $permitidas
     */
    private function bloquear_em_situacao(Afiliados $afiliado, array $permitidas, string $mensagem): Afiliados
    {
        $afiliado = Afiliados::lockForUpdate()->findOrFail($afiliado->id);

        if (! in_array($afiliado->situacao, $permitidas, true)) {
            throw ValidationException::withMessages(['situacao' => $mensagem]);
        }

        return $afiliado;
    }

    /**
     * Envia pela fila depois do commit; uma falha no envio nunca desfaz o cadastro nem a decisão.
     */
    private function enviar_email(Afiliados $afiliado, Mailable $email): void
    {
        if ($afiliado->email === null) {
            return;
        }

        try {
            Mail::to($afiliado->email)->queue($email);
        } catch (Throwable $erro) {
            Log::error('Falha ao enviar o e-mail do afiliado.', ['afiliados_id' => $afiliado->id, 'erro' => $erro->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criar(array $dados, SituacaoAfiliado $situacao, OrigemCadastroAfiliado $origem, ?Usuarios $autor): Afiliados
    {
        try {
            return DB::transaction(function () use ($dados, $situacao, $origem, $autor) {
                $afiliado = new Afiliados([
                    ...Arr::except($dados, [...self::CAMPOS_PIX, ...self::CAMPOS_REGRA, 'password_confirmation']),
                    'codigo' => Afiliados::gerar_codigo(),
                    'origem_cadastro' => $origem,
                    'cadastrado_por' => $autor?->id,
                ]);
                $afiliado->forceFill(['situacao' => $situacao])->save();

                $afiliado->meio_pagamento()->create(Arr::only($dados, self::CAMPOS_PIX));
                $this->regras->definir_inicial($afiliado, Arr::only($dados, self::CAMPOS_REGRA), $autor);

                return $afiliado;
            });
        } catch (UniqueConstraintViolationException) {
            // dois cadastros simultâneos com o mesmo dado único: o segundo cai aqui
            throw ValidationException::withMessages([
                'login' => 'O login, o e-mail ou o CPF já está cadastrado.',
            ]);
        }
    }
}
