<?php

namespace App\Jobs;

use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Enums\TipoEstornoBonus;
use App\Models\ClientesPromocoes;
use App\Models\ClientesPromocoesEstornos;
use App\Models\ClientesTransacoes;
use App\Services\EstornoBonus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Estorna uma promoção de todos que a receberam, bônus a bônus e em lotes (spec 020, R-05): cada
 * crédito passa pelo EstornoBonus, que só retira o que ainda está em andamento ou cumprido e pula os
 * convertidos, expirados, excluídos e já estornados. Idempotente por bônus: pode ser retomado sem
 * retirar nada duas vezes.
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

    public function handle(EstornoBonus $estorno): void
    {
        $promocao = ClientesPromocoes::withTrashed()->with('autor_estorno')->findOrFail($this->promocao_id);
        $clientes_avaliados = [];

        $this->creditos()->chunkById(self::TAMANHO_LOTE, function (Collection $lote) use ($promocao, $estorno, &$clientes_avaliados) {
            foreach ($lote as $credito) {
                $estorno->estornar($credito, TipoEstornoBonus::Geral, $promocao->autor_estorno, (string) $promocao->estorno_motivo);
                $clientes_avaliados[$credito->clientes_id] = true;
            }

            $this->atualizar_contadores($promocao, count($clientes_avaliados));
        });

        $this->atualizar_contadores($promocao, count($clientes_avaliados));
        $promocao->forceFill([
            'estorno_situacao' => SituacaoEstorno::Concluído,
            'estorno_concluido_em' => now(),
        ])->save();
    }

    /**
     * Créditos da promoção (um por bônus recebido).
     */
    private function creditos()
    {
        return ClientesTransacoes::query()
            ->where('origem', OrigemTransacao::Promoção)
            ->where('referencia_id', $this->promocao_id);
    }

    /**
     * Recalcula o valor a partir do banco (não soma), para uma retomada não contar em dobro.
     */
    private function atualizar_contadores(ClientesPromocoes $promocao, int $clientes_avaliados): void
    {
        $promocao->forceFill([
            'estorno_clientes_processados' => $clientes_avaliados,
            'estorno_valor_total' => (string) (ClientesPromocoesEstornos::where('clientes_promocoes_id', $this->promocao_id)
                ->where('tipo', TipoEstornoBonus::Geral)
                ->sum('valor_retirado') ?: '0'),
        ])->save();
    }
}
