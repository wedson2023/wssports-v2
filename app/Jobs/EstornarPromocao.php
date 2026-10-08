<?php

namespace App\Jobs;

use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Models\Clientes;
use App\Models\ClientesPromocoes;
use App\Models\ClientesTransacoes;
use App\Services\RolloverClientes;
use App\Services\SaldoClientes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Estorna uma promoção de todos os clientes que a receberam, em lotes, só no saldo
 * promocional da modalidade. Idempotente: pode ser retomado sem estornar alguém duas vezes.
 */
class EstornarPromocao implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const TAMANHO_LOTE = 500;

    public int $tries = 5;

    public function __construct(public int $promocao_id) {}

    public function uniqueId(): string
    {
        return (string) $this->promocao_id;
    }

    public function handle(SaldoClientes $saldo, RolloverClientes $rollover): void
    {
        $promocao = ClientesPromocoes::withTrashed()->findOrFail($this->promocao_id);
        $clientes_avaliados = 0;

        // a consulta agrupada não tem "id": o lote avança pela coluna clientes_id
        $this->consulta_recebimentos()->chunkById(self::TAMANHO_LOTE, function (Collection $lote) use ($promocao, $saldo, $rollover, &$clientes_avaliados) {
            foreach ($lote as $recebimento) {
                // o bônus estornado deixa de exigir rollover e suas regras de uso deixam de valer (spec 004)
                $rollover->cancelar_da_promocao((int) $recebimento->clientes_id, $promocao->id);
                $this->estornar_cliente($promocao, $saldo, (int) $recebimento->clientes_id, (string) $recebimento->total_recebido);
            }

            $clientes_avaliados += $lote->count();
            $this->atualizar_contadores($promocao, $clientes_avaliados);
        }, 'clientes_id');

        $this->atualizar_contadores($promocao, $clientes_avaliados);
        $promocao->forceFill([
            'estorno_situacao' => SituacaoEstorno::Concluído,
            'estorno_concluido_em' => now(),
        ])->save();
    }

    private function estornar_cliente(ClientesPromocoes $promocao, SaldoClientes $saldo, int $clientes_id, string $total_recebido): void
    {
        // idempotência: quem já tem estorno desta promoção foi processado numa execução anterior
        if ($this->transacoes_da_promocao(OrigemTransacao::Estorno)->where('clientes_id', $clientes_id)->exists()) {
            return;
        }

        $cliente = Clientes::withTrashed()->find($clientes_id);

        if ($cliente === null) {
            return;
        }

        $carteira = $promocao->modalidade->carteira();
        $saldo_atual = SaldoClientes::para_centavos((string) $cliente->getRawOriginal($carteira->coluna()));

        // retira o recebido ou, se não houver tudo isso, zera o saldo promocional; nada fica pendente
        $valor = min($saldo_atual, SaldoClientes::para_centavos($total_recebido));

        if ($valor <= 0) {
            return;
        }

        $saldo->debitar(
            $cliente,
            $carteira,
            SaldoClientes::para_decimal($valor),
            OrigemTransacao::Estorno,
            $promocao->autor_estorno,
            $promocao->id,
            $promocao->estorno_motivo,
        );
    }

    /**
     * Clientes que receberam a promoção, com o total recebido por cliente.
     */
    private function consulta_recebimentos()
    {
        return $this->transacoes_da_promocao(OrigemTransacao::Promoção)
            ->selectRaw('clientes_id, SUM(valor) as total_recebido')
            ->groupBy('clientes_id');
    }

    private function transacoes_da_promocao(OrigemTransacao $origem)
    {
        return ClientesTransacoes::query()
            ->where('origem', $origem)
            ->where('referencia_id', $this->promocao_id);
    }

    /**
     * Recalcula os contadores a partir do banco (não soma), para uma retomada não contar em dobro.
     */
    private function atualizar_contadores(ClientesPromocoes $promocao, int $clientes_avaliados): void
    {
        $promocao->forceFill([
            'estorno_clientes_processados' => $clientes_avaliados,
            'estorno_valor_total' => (string) ($this->transacoes_da_promocao(OrigemTransacao::Estorno)->sum('valor') ?: '0'),
        ])->save();
    }
}
