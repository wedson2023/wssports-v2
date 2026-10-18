<?php

namespace App\Services;

use App\Models\Afiliados;
use App\Models\AfiliadosPrestacoesContas;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Prestação de contas do afiliado (spec 012, FR-035 a FR-041, research R-07): fecha todos os
 * lançamentos em aberto até agora e zera o valor a receber; saldo negativo é zerado e não passa
 * para o período seguinte. O bloqueio do afiliado impede duas prestações de fecharem o mesmo
 * lançamento.
 */
class PrestacaoContasAfiliados
{
    public function prestar(Afiliados $afiliado, Usuarios $autor, ?string $observacao): AfiliadosPrestacoesContas
    {
        return DB::transaction(function () use ($afiliado, $autor, $observacao) {
            $afiliado = Afiliados::lockForUpdate()->findOrFail($afiliado->id);
            $agora = now();

            $lancamentos = $afiliado->lancamentos()->emAberto()->lockForUpdate()->get(['id', 'valor']);
            $apurado = $lancamentos->reduce(fn (string $soma, $lancamento) => bcadd($soma, (string) $lancamento->valor, 2), '0.00');

            $prestacao = $afiliado->prestacoes()->create([
                'valor_apurado' => $apurado,
                'valor_pago' => bccomp($apurado, '0', 2) > 0 ? $apurado : '0.00',
                'quantidade_lancamentos' => $lancamentos->count(),
                'periodo_inicio' => $afiliado->prestacoes()->max('periodo_fim') ?? $afiliado->created_at,
                'periodo_fim' => $agora,
                'usuarios_id' => $autor->id,
                'observacao' => $observacao,
            ]);

            if ($lancamentos->isNotEmpty()) {
                $afiliado->lancamentos()
                    ->whereIn('id', $lancamentos->pluck('id'))
                    ->update(['afiliados_prestacoes_contas_id' => $prestacao->id]);
            }

            return $prestacao;
        });
    }
}
