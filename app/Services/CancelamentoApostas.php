<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\OrigemTransacao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Support\Facades\DB;

/**
 * Cancelamento da aposta (FR-050 a FR-052): o vendedor, nas próprias apostas, dentro do tempo e
 * antes de qualquer jogo; a hierarquia, sem limite de tempo. Devolve exatamente o que a aposta
 * moveu, uma única vez.
 */
class CancelamentoApostas
{
    private const TAMANHO_USER_AGENT = 255;

    public function __construct(
        private AlcanceApostas $alcance,
        private SaldoClientes $saldo,
        private RolloverClientes $rollover,
        private RegrasExibicao $exibicao,
    ) {}

    /**
     * @throws RegraApostaException
     */
    public function cancelar(Usuarios $autor, string $codigo, ?string $ip, ?string $user_agent): Apostas
    {
        return DB::transaction(function () use ($autor, $codigo, $ip, $user_agent) {
            // ordem de bloqueio (R-07): aposta → vendedor ou cliente → rollovers
            $aposta = Apostas::buscar_pelo_codigo($codigo)->lockForUpdate()->first();

            abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

            $this->alcance->garantir_pode_cancelar($autor, $aposta);
            $this->conferir_situacao($aposta);

            if ($autor->funcao() === Funcao::Vendedor) {
                $this->conferir_regras_vendedor($autor, $aposta);
            } elseif ($this->tem_jogo_iniciado($aposta) && ! Funcao::usuario_pode($autor, 'apostas.cancelar_iniciada')) {
                throw new RegraApostaException('Não é possível cancelar depois do início de um jogo.');
            }

            $this->devolver($autor, $aposta);

            $aposta->fill([
                'situacao' => SituacaoAposta::Cancelada,
                'cancelada_em' => now(),
                'cancelada_por' => $autor->id,
                'ip_cancelamento' => $ip,
                'user_agent_cancelamento' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
            ])->save();

            return $aposta;
        });
    }

    /**
     * Só aposta Ativa com resultado Aguardando (FR-052).
     *
     * @throws RegraApostaException
     */
    private function conferir_situacao(Apostas $aposta): void
    {
        if ($aposta->situacao === SituacaoAposta::Cancelada) {
            throw new RegraApostaException('Esta aposta já foi cancelada.');
        }

        if ($aposta->situacao !== SituacaoAposta::Ativa) {
            throw new RegraApostaException('Só apostas ativas podem ser canceladas.');
        }

        if ($aposta->resultado !== ResultadoAposta::Aguardando) {
            throw new RegraApostaException('Não é possível cancelar uma aposta já apurada.');
        }
    }

    /**
     * Vendedor: permissão nas configurações, sem trava do sistema, dentro do tempo gravado na
     * aposta, sem jogo ao vivo e sem jogo iniciado (FR-050).
     *
     * @throws RegraApostaException
     */
    private function conferir_regras_vendedor(Usuarios $vendedor, Apostas $aposta): void
    {
        $configuracao = UsuariosConfiguracoes::do_vendedor($vendedor->id);

        if (! $configuracao->cancelar_aposta) {
            throw new RegraApostaException('Você não tem permissão para cancelar apostas.');
        }

        if ($this->exibicao->sistema_travado(new Publico(Publico::VENDEDOR, usuario: $vendedor))) {
            throw new RegraApostaException('Sistema travado, procure seu gerente.');
        }

        $minutos = (int) $aposta->tempo_cancelamento_aposta;
        $inicio = $aposta->validada_em ?? $aposta->confirmada_em;

        if ($inicio === null || $inicio->copy()->addMinutes($minutos)->lessThan(now())) {
            throw new RegraApostaException("O seu tempo de {$minutos} minuto(s) para cancelar terminou.");
        }

        if ($aposta->palpites()->whereNotNull('confrontos_ao_vivo_id')->exists()) {
            throw new RegraApostaException('Não é possível cancelar apostas com jogos ao vivo.');
        }

        if ($this->tem_jogo_iniciado($aposta)) {
            throw new RegraApostaException('Não é possível cancelar depois do início de um jogo.');
        }
    }

    /**
     * Algum jogo da aposta já começou (palpite do ao vivo conta como iniciado).
     */
    private function tem_jogo_iniciado(Apostas $aposta): bool
    {
        return $aposta->palpites()->whereNotNull('confrontos_ao_vivo_id')->exists()
            || DB::table('apostas_palpites as ap')
                ->join('confrontos as co', 'co.id', '=', 'ap.confrontos_id')
                ->where('ap.apostas_id', $aposta->id)
                ->whereNull('ap.deleted_at')
                ->where('co.data_inicio', '<=', now())
                ->exists();
    }

    /**
     * Cliente: o valor volta à carteira de onde saiu e o rollover somado é desfeito. Vendedor: os
     * limites abatidos na confirmação voltam (FR-051).
     */
    private function devolver(Usuarios $autor, Apostas $aposta): void
    {
        $valor = (string) $aposta->valor;

        if ($aposta->clientes_id !== null) {
            $this->saldo->creditar(
                Clientes::withTrashed()->findOrFail($aposta->clientes_id),
                $aposta->forma_pagamento->carteira(),
                $valor,
                OrigemTransacao::Estorno,
                $autor,
                $aposta->id,
                "Cancelamento da aposta {$aposta->codigo}",
            );
            $this->rollover->desfazer($aposta);

            return;
        }

        $configuracao = UsuariosConfiguracoes::where('usuarios_id', $aposta->usuarios_id)->lockForUpdate()->firstOrFail();
        // a quantidade da confirmação inclui os palpites cancelados depois por edição
        $limite = $aposta->palpites()->count() === 1 ? 'limite_simples' : 'limite_duplo';

        foreach ([$limite, 'limite_geral'] as $coluna) {
            $configuracao->{$coluna} = bcadd((string) $configuracao->{$coluna}, $valor, 2);
        }

        $configuracao->save();
    }
}
