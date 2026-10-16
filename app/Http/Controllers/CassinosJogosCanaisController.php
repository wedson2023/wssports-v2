<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CassinosJogosCanaisRequest;
use App\Models\CassinosJogosCanais;
use App\Services\ListagemCassino;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Canais dos jogos do cassino no painel (spec 011, FR-030, R-06): liberar, prioridade, pré-pago,
 * "bônus primeiro" e liberar o canal recusado pelo provedor antes do prazo.
 */
class CassinosJogosCanaisController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_jogos.gerenciar')];
    }

    public function update(CassinosJogosCanaisRequest $request, CassinosJogosCanais $canal): JsonResponse
    {
        $dados = $request->safe()->except('disponivel');

        if ($request->has('disponivel')) {
            $dados = [...$dados, 'indisponivel_em' => null, 'motivo_indisponibilidade' => null];
        }

        $canal->forceFill($dados)->save();
        ListagemCassino::limpar_cache();

        return response()->json(['data' => $canal->only([
            'id', 'cassinos_jogos_id', 'canal', 'codigo_provedor', 'pre_pago', 'bonus_primeiro', 'prioridade', 'ativo', 'demo', 'flash', 'indisponivel_em', 'motivo_indisponibilidade', 'removido_em',
        ])]);
    }
}
