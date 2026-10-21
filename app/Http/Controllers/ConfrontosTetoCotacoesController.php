<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\ConfrontosTetoCotacoes;
use App\Services\HistoricoCotacoes;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

/**
 * Teto de cotação por código, igual para todos os públicos. Só Admin e Supervisor.
 */
class ConfrontosTetoCotacoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos_teto_cotacoes.editar'),
        ];
    }

    public function show(): JsonResponse
    {
        return response()->json(['tetos' => (object) (ConfrontosTetoCotacoes::atual()->tetos ?? [])]);
    }

    public function update(RegrasCotacaoRequest $request, RegrasCotacao $regras, HistoricoCotacoes $historico): JsonResponse
    {
        $teto = ConfrontosTetoCotacoes::atual();
        $antes = $teto->tetos ?? [];
        $teto->tetos = $regras->aplicar($antes, $request->validated('valores'), $request->validated('todos'));

        // gravação e histórico juntos (spec 016, FR-018a)
        DB::transaction(function () use ($teto, $request, $antes, $historico) {
            $teto->save();
            $historico->registrar($request->user(), 'tetos', HistoricoCotacoes::ALTERAR, [], $antes, $teto->tetos);
        });

        return response()->json(['tetos' => (object) $teto->tetos]);
    }
}
