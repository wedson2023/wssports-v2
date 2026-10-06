<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\ConfrontosTetoCotacoes;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

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

    public function update(RegrasCotacaoRequest $request, RegrasCotacao $regras): JsonResponse
    {
        $teto = ConfrontosTetoCotacoes::atual();
        $teto->tetos = $regras->aplicar($teto->tetos ?? [], $request->validated('valores'), $request->validated('todos'));
        $teto->save();

        return response()->json(['tetos' => (object) $teto->tetos]);
    }
}
