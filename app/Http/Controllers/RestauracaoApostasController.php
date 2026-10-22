<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\ComprovanteApostaResource;
use App\Services\RestauracaoApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Restauração de aposta cancelada pelo painel (spec 018, R-13): Admin, Supervisor e Gerente com a
 * permissão, na própria rede.
 */
class RestauracaoApostasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.restaurar'),
        ];
    }

    public function store(Request $request, string $codigo, RestauracaoApostas $restauracao): JsonResponse
    {
        $aposta = $restauracao->restaurar($request->user(), $codigo, $request->ip(), $request->userAgent());

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), mostrar_comissao: true)]);
    }
}
