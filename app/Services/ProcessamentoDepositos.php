<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\CategoriaPromocao;
use App\Enums\MeioDeposito;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoDeposito;
use App\Models\Clientes;
use App\Models\ClientesRollovers;
use App\Models\Configuracoes;
use App\Models\Depositos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Situação dos depósitos a partir do Mercado Pago (spec 007, FR-029 a FR-032, research R-10 a
 * R-12). Cada mudança acontece com o depósito bloqueado e só a partir dos estados permitidos, então
 * o mesmo aviso repetido não credita duas vezes.
 */
class ProcessamentoDepositos
{
    public function __construct(
        private SaldoClientes $saldo,
        private RolloverClientes $rollover,
        private EscolhaPromocoes $promocoes,
        private Indicacoes $indicacoes,
        private ComissoesAfiliados $comissoes,
        private EstornoBonus $estorno_bonus,
    ) {}

    /**
     * Aplica o pagamento consultado no Mercado Pago ao depósito dele.
     *
     * @param  array<string, mixed>  $pagamento  resposta de GET /v1/payments/{id}
     * @return bool se o pagamento pertence a um depósito do sistema
     */
    public function atualizar(array $pagamento): bool
    {
        $deposito = Depositos::where('codigo', $pagamento['external_reference'] ?? '')
            ->orWhere('pagamento_id', (string) ($pagamento['id'] ?? ''))
            ->first();

        if ($deposito === null) {
            return false;
        }

        DB::transaction(function () use ($deposito, $pagamento) {
            $deposito = Depositos::lockForUpdate()->findOrFail($deposito->id);
            $status = $pagamento['status'] ?? null;

            match ($status) {
                'approved' => $this->aprovar($deposito, $pagamento),
                'authorized', 'in_process', 'pending' => $this->em_analise($deposito),
                'rejected' => $this->finalizar($deposito, SituacaoDeposito::Recusado, $pagamento['status_detail'] ?? null),
                'cancelled' => $this->finalizar($deposito, $this->vencido($deposito) ? SituacaoDeposito::Expirado : SituacaoDeposito::Cancelado, $pagamento['status_detail'] ?? null),
                'refunded', 'charged_back' => $this->estornar($deposito, (string) ($pagamento['transaction_amount_refunded'] ?? $deposito->valor)),
                default => null,
            };
        });

        return true;
    }

    /**
     * Pix vencidos sem pagamento passam a Expirado (o Mercado Pago também os cancela).
     */
    public function expirar_vencidos(): int
    {
        return Depositos::where('situacao', SituacaoDeposito::Pendente)
            ->where('meio', MeioDeposito::Pix)
            ->where('expira_em', '<', now())
            ->update(['situacao' => SituacaoDeposito::Expirado, 'updated_at' => now()]);
    }

    /**
     * Crédito do depósito, cashback, bônus de depósito e rollover, numa operação (R-11).
     *
     * @param  array<string, mixed>  $pagamento
     */
    private function aprovar(Depositos $deposito, array $pagamento): void
    {
        if (! in_array($deposito->situacao, [SituacaoDeposito::Pendente, SituacaoDeposito::EmAnálise, SituacaoDeposito::Expirado, SituacaoDeposito::Cancelado], true)) {
            return;
        }

        $cliente = Clientes::withTrashed()->findOrFail($deposito->clientes_id);
        $configuracoes = Configuracoes::atual();
        $autor = $deposito->autor;
        $valor = (string) $deposito->valor;
        $primeiro_deposito = ! Depositos::where('clientes_id', $cliente->id)
            ->where('id', '!=', $deposito->id)
            ->whereIn('situacao', [SituacaoDeposito::Aprovado, SituacaoDeposito::Estornado])
            ->exists();

        // 1. depósito no saldo (cobre primeiro um saldo negativo: é só uma soma)
        $transacao = $this->saldo->creditar($cliente, Carteira::Saldo, $valor, OrigemTransacao::Depósito, $autor, $deposito->id, "Depósito via {$deposito->meio->value}");

        // 2. cashback, somado ao rollover do depósito
        $cashback = '0.00';

        if (bccomp((string) $configuracoes->cashback_percentual, '0', 2) > 0) {
            $cashback = EscolhaPromocoes::percentual($valor, (string) $configuracoes->cashback_percentual);

            if (bccomp($cashback, '0', 2) > 0) {
                $this->saldo->creditar($cliente, Carteira::Saldo, $cashback, OrigemTransacao::Cashback, $autor, $deposito->id, 'Cashback do depósito');
            }
        }

        // 3. promoção de depósito, se o cliente aceitou bônus
        $promocao = $deposito->aceita_bonus ? $this->promocoes->para_deposito($cliente, $valor, $primeiro_deposito) : null;
        $rollover_bonus = null;

        if ($promocao !== null) {
            $valor_bonus = $this->promocoes->valor_do_bonus($promocao, $valor);

            if (bccomp($valor_bonus, '0', 2) > 0) {
                $bonus = $this->saldo->creditar($cliente, $promocao->modalidade->carteira(), $valor_bonus, OrigemTransacao::Promoção, $autor, $promocao->id, "Promoção: {$promocao->nome}");
                $rollover_bonus = $this->rollover->criar_bonus($cliente, $bonus, $promocao);
            }
        }

        // 4. rollover do depósito: vezes da promoção com "rollover primeiro depósito", senão o padrão
        $vezes = match (true) {
            $promocao !== null && $promocao->rollover_primeiro_deposito && $promocao->categoria === CategoriaPromocao::PrimeiroDepósito => (int) $promocao->rollover,
            $configuracoes->rollover_deposito => $configuracoes->rollover_deposito_vezes,
            default => 0,
        };
        $rollover_deposito = $this->rollover->criar_deposito($cliente, $transacao, $vezes, $cashback);

        $deposito->forceFill([
            'situacao' => SituacaoDeposito::Aprovado,
            'pago_em' => isset($pagamento['date_approved']) ? Carbon::parse($pagamento['date_approved']) : now(),
            'status_detalhe' => $pagamento['status_detail'] ?? null,
            'clientes_transacoes_id' => $transacao->id,
            'clientes_promocoes_id' => $promocao?->id,
            'bonus_clientes_rollovers_id' => $rollover_bonus?->id,
            'rollover_clientes_rollovers_id' => $rollover_deposito?->id,
        ])->save();

        $this->depois_de_aprovar($cliente, $deposito, $primeiro_deposito);
    }

    /**
     * O primeiro depósito aprovado valida a indicação do cliente (US9); todo depósito aprovado gera
     * a comissão do afiliado do cliente, conforme a regra dele (spec 012).
     */
    private function depois_de_aprovar(Clientes $cliente, Depositos $deposito, bool $primeiro_deposito): void
    {
        if ($primeiro_deposito) {
            $this->indicacoes->validar($cliente, $deposito);
        }

        $this->comissoes->registrar_deposito($deposito, $primeiro_deposito);
    }

    /**
     * O estorno total desfaz a indicação validada por este depósito (US9).
     */
    private function depois_de_estornar(Depositos $deposito): void
    {
        $this->indicacoes->desfazer($deposito);
    }

    private function em_analise(Depositos $deposito): void
    {
        if ($deposito->meio === MeioDeposito::CartãoCrédito && $deposito->situacao === SituacaoDeposito::Pendente) {
            $deposito->forceFill(['situacao' => SituacaoDeposito::EmAnálise])->save();
        }
    }

    private function finalizar(Depositos $deposito, SituacaoDeposito $situacao, ?string $detalhe): void
    {
        if (in_array($deposito->situacao, [SituacaoDeposito::Pendente, SituacaoDeposito::EmAnálise], true)) {
            $deposito->forceFill(['situacao' => $situacao, 'status_detalhe' => $detalhe])->save();
        }
    }

    /**
     * Estorno ou chargeback: debita só o que ainda não foi estornado, mesmo deixando o saldo
     * negativo (decisão da spec); no estorno total, cancela o rollover do depósito e o bônus dele.
     */
    private function estornar(Depositos $deposito, string $total_estornado): void
    {
        if ($deposito->situacao !== SituacaoDeposito::Aprovado) {
            return;
        }

        $valor = (string) $deposito->valor;
        $total = bccomp($total_estornado, $valor, 2) > 0 ? $valor : $total_estornado;
        $diferenca = bcsub($total, (string) $deposito->valor_estornado, 2);

        if (bccomp($diferenca, '0', 2) <= 0) {
            return;
        }

        $cliente = Clientes::withTrashed()->findOrFail($deposito->clientes_id);
        $this->saldo->debitar($cliente, Carteira::Saldo, $diferenca, OrigemTransacao::EstornoDepósito, null, $deposito->id, 'Estorno do depósito pelo meio de pagamento', permitir_negativo: true);

        $total_estornado_agora = bccomp($total, $valor, 2) === 0;
        $deposito->forceFill([
            'valor_estornado' => $total,
            'situacao' => $total_estornado_agora ? SituacaoDeposito::Estornado : SituacaoDeposito::Aprovado,
            'estornado_em' => now(),
        ])->save();

        // estorno parcial ou total desconta a comissão do afiliado (spec 012, FR-030); depois de
        // gravar o novo valor_estornado, que entra na chave do lançamento
        $this->comissoes->registrar_estorno($deposito, $diferenca);

        if (! $total_estornado_agora) {
            return;
        }

        if ($deposito->rollover_deposito !== null) {
            $this->rollover->cancelar($deposito->rollover_deposito);
        }

        $this->cancelar_bonus($cliente, $deposito->rollover_bonus);
        $this->depois_de_estornar($deposito);
    }

    /**
     * Retira o que restou do bônus ligado ao depósito estornado e cancela o rollover dele.
     */
    private function cancelar_bonus(Clientes $cliente, ?ClientesRollovers $bonus): void
    {
        if ($bonus === null || $bonus->cancelado_em !== null || $bonus->expirado_em !== null || $bonus->excluido_em !== null) {
            return;
        }

        $cliente->refresh();
        $saldo_carteira = (string) $cliente->{$bonus->carteira->coluna()};
        $retirar = bccomp($saldo_carteira, (string) $bonus->valor_creditado, 2) < 0 ? $saldo_carteira : (string) $bonus->valor_creditado;

        $debito = null;

        if (bccomp($retirar, '0', 2) > 0) {
            $debito = $this->saldo->debitar($cliente, $bonus->carteira, $retirar, OrigemTransacao::BônusExcluído, null, $bonus->clientes_promocoes_id, 'Bônus cancelado pelo estorno do depósito');
        }

        $bonus->forceFill(['cancelado_em' => now(), 'excluido_em' => now()])->save();

        // aparece como Estornado (automático) na tela Bônus, não como excluído pelo cliente (spec 020)
        $this->estorno_bonus->registrar_automatico($bonus->clientes_transacoes_id, $bonus, 'Cancelado pelo estorno do depósito', $debito === null ? '0.00' : $retirar, $debito);
    }

    private function vencido(Depositos $deposito): bool
    {
        return $deposito->meio === MeioDeposito::Pix && $deposito->expira_em !== null && $deposito->expira_em->isPast();
    }
}
