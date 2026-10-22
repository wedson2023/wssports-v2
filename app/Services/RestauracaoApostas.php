<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\OrigemApuracao;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoAposta;
use App\Exceptions\RegraApostaException;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\Apostas;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Support\Facades\DB;

/**
 * Restauração de aposta cancelada pelo painel (spec 018, R-13): o inverso exato do
 * CancelamentoApostas. Admin, Supervisor e Gerente com a permissão, na própria rede; com jogo já
 * iniciado, só quem pode cancelar aposta iniciada, e a aposta é apurada de novo. O valor volta a sair
 * do limite do vendedor ou da carteira do cliente (com o rollover); sem limite ou saldo, recusa.
 */
class RestauracaoApostas
{
    private const TAMANHO_USER_AGENT = 255;

    public function __construct(
        private AlcanceApostas $alcance,
        private SaldoClientes $saldo,
        private RolloverClientes $rollover,
        private CancelamentoApostas $cancelamento,
        private ApuracaoApostas $apuracao,
    ) {}

    /**
     * @throws RegraApostaException
     */
    public function restaurar(Usuarios $autor, string $codigo, ?string $ip, ?string $user_agent): Apostas
    {
        $iniciada = false;

        $aposta = DB::transaction(function () use ($autor, $codigo, $ip, $user_agent, &$iniciada) {
            // mesma ordem de bloqueio do cancelamento: aposta → vendedor ou cliente → rollovers
            $aposta = Apostas::buscar_pelo_codigo($codigo)->lockForUpdate()->first();

            abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

            $this->alcance->garantir_pode_editar($autor, $aposta);

            // apostas do site só para quem as vê na lista: Admin e Supervisor (FR-003)
            abort_if(
                $aposta->clientes_id !== null && ! in_array($autor->funcao(), [Funcao::Admin, Funcao::Supervisor], true),
                404,
                'Aposta não encontrada.',
            );

            if ($aposta->situacao !== SituacaoAposta::Cancelada) {
                throw new RegraApostaException('Só apostas canceladas podem ser restauradas.');
            }

            // com encerramento parcial (spec 010) o valor e o rollover já foram divididos: o
            // cancelamento devolveu só uma parte, e somar de novo não refaz o mesmo estado
            if (! $this->pode_restaurar_valores($aposta)) {
                throw new RegraApostaException('Não é possível restaurar uma aposta com encerramento parcial.');
            }

            $iniciada = $this->cancelamento->tem_jogo_iniciado($aposta);

            if ($iniciada && ! Funcao::usuario_pode($autor, 'apostas.cancelar_iniciada')) {
                throw new RegraApostaException('Não é possível restaurar depois do início de um jogo.');
            }

            $this->cobrar($autor, $aposta);

            $aposta->fill([
                'situacao' => SituacaoAposta::Ativa,
                'restaurada_em' => now(),
                'restaurada_por' => $autor->id,
                'ip_restauracao' => $ip,
                'user_agent_restauracao' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
            ])->save();

            return $aposta;
        });

        // com jogo já iniciado, algum pode ter terminado enquanto a aposta estava cancelada (a apuração
        // pula as canceladas): apura de novo, depois de gravada a restauração
        if ($iniciada) {
            $this->apuracao->apurar_aposta($aposta->id, OrigemApuracao::ReprocessamentoManual);
        }

        return $aposta;
    }

    /**
     * A aposta não teve encerramento parcial (a fração que continua valendo é a aposta inteira).
     */
    public function pode_restaurar_valores(Apostas $aposta): bool
    {
        return bccomp((string) ($aposta->fracao_restante ?? '1'), '1', 10) === 0;
    }

    /**
     * Inverso do CancelamentoApostas::devolver: cliente, o valor que continua valendo sai de novo da
     * carteira de onde tinha saído e o rollover é somado de novo; vendedor, os limites voltam a ser
     * abatidos. Sem saldo ou limite suficiente, recusa sem mudar nada.
     *
     * @throws RegraApostaException
     */
    private function cobrar(Usuarios $autor, Apostas $aposta): void
    {
        if ($aposta->clientes_id !== null) {
            $valor_em_jogo = $aposta->valor_em_jogo();

            if (bccomp($valor_em_jogo, '0', 2) > 0) {
                try {
                    $this->saldo->debitar(
                        Clientes::withTrashed()->findOrFail($aposta->clientes_id),
                        $aposta->forma_pagamento->carteira(),
                        $valor_em_jogo,
                        OrigemTransacao::Aposta,
                        $autor,
                        $aposta->id,
                        "Restauração da aposta {$aposta->codigo}",
                    );
                } catch (SaldoInsuficienteException) {
                    throw new RegraApostaException('O cliente não tem saldo para restaurar esta aposta.');
                }
            }

            $this->rollover->somar($aposta);

            return;
        }

        $valor = (string) $aposta->valor;
        $configuracao = UsuariosConfiguracoes::where('usuarios_id', $aposta->usuarios_id)->lockForUpdate()->firstOrFail();
        // mesma escolha do cancelamento: a quantidade da confirmação inclui os palpites cancelados depois
        $limite = $aposta->palpites()->count() === 1 ? 'limite_simples' : 'limite_duplo';

        foreach ([$limite, 'limite_geral'] as $coluna) {
            $novo = bcsub((string) $configuracao->{$coluna}, $valor, 2);

            if (bccomp($novo, '0', 2) < 0) {
                throw new RegraApostaException('O vendedor não tem limite para restaurar esta aposta.');
            }

            $configuracao->{$coluna} = $novo;
        }

        $configuracao->save();
    }
}
