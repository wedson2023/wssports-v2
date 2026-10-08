<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\ComprovanteApostaResource;
use App\Services\CancelamentoApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Cancelamento de aposta pelo vendedor (as próprias, no tempo) e pela hierarquia.
 */
class CancelamentoApostasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.cancelar'),
        ];
    }

    public function store(Request $request, string $codigo, CancelamentoApostas $cancelamento): JsonResponse
    {
        $aposta = $cancelamento->cancelar($request->user(), $codigo, $request->ip(), $request->userAgent());

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), mostrar_comissao: true)]);
    }
}
