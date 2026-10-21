<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\TipoLancamentoCaixa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saldo de caixa dos vendedores (spec 017, FR-009, research R-03): o que o vendedor deve, calculado
 * como "total devido − total recebido":
 *
 *   Σ (valor − comissão − saída atual) das apostas ativas do balcão feitas até o instante
 *   + Σ suprimentos − Σ retiradas até o instante
 *   − Σ valor recebido das prestações não desfeitas.
 *
 * Entre duas prestações isso é o saldo anterior + o movimento do intervalo, e trata sozinho o prêmio
 * de aposta decidida depois da prestação, o cancelamento depois da prestação, a diferença do valor
 * recebido, a prestação desfeita e a re-apuração: a aposta entra sempre com a situação de agora.
 */
class SaldoCaixa
{
    /**
     * Saldo de caixa de cada vendedor até o instante (agora quando vazio); vendedor sem movimento
     * vale zero.
     *
     * @param  list<int>  $ids_vendedores
     * @return array<int, string>
     */
    public function saldos(array $ids_vendedores, ?Carbon $ate = null): array
    {
        if ($ids_vendedores === []) {
            return [];
        }

        $saldos = array_fill_keys($ids_vendedores, '0.00');

        foreach ($this->devido_apostas($ids_vendedores, $ate) as $id => $valor) {
            $saldos[$id] = bcadd($saldos[$id], $valor, 2);
        }

        foreach ($this->lancamentos($ids_vendedores, $ate) as $id => $valor) {
            $saldos[$id] = bcadd($saldos[$id], $valor, 2);
        }

        foreach ($this->recebido($ids_vendedores) as $id => $valor) {
            $saldos[$id] = bcsub($saldos[$id], $valor, 2);
        }

        return $saldos;
    }

    /**
     * Σ (valor − comissão − saída atual) das apostas ativas do balcão de cada vendedor.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function devido_apostas(array $ids, ?Carbon $ate): array
    {
        return DB::table('apostas')
            ->whereIn('usuarios_id', $ids)
            ->whereNull('clientes_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoAposta::Ativa->value)
            ->when($ate, fn ($consulta) => $consulta->where('created_at', '<=', $ate))
            ->groupBy('usuarios_id')
            ->select('usuarios_id')
            ->selectRaw(
                'COALESCE(SUM(valor - comissao - CASE WHEN resultado = ? THEN premio WHEN resultado = ? THEN valor ELSE 0 END), 0) as devido',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->pluck('devido', 'usuarios_id')
            ->map(fn ($valor) => bcadd((string) $valor, '0', 2))
            ->all();
    }

    /**
     * Σ suprimentos − Σ retiradas não excluídos de cada vendedor.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function lancamentos(array $ids, ?Carbon $ate): array
    {
        return DB::table('caixa_lancamentos')
            ->whereIn('usuarios_id', $ids)
            ->whereNull('deleted_at')
            ->when($ate, fn ($consulta) => $consulta->where('ocorrido_em', '<=', $ate))
            ->groupBy('usuarios_id')
            ->select('usuarios_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE -valor END), 0) as total',
                [TipoLancamentoCaixa::Suprimento->value],
            )
            ->pluck('total', 'usuarios_id')
            ->map(fn ($valor) => bcadd((string) $valor, '0', 2))
            ->all();
    }

    /**
     * Σ valor recebido das prestações não desfeitas de cada vendedor.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function recebido(array $ids): array
    {
        return DB::table('caixa_prestacoes_contas')
            ->whereIn('usuarios_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('usuarios_id')
            ->select('usuarios_id')
            ->selectRaw('COALESCE(SUM(valor_recebido), 0) as total')
            ->pluck('total', 'usuarios_id')
            ->map(fn ($valor) => bcadd((string) $valor, '0', 2))
            ->all();
    }
}
