<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\TabelaJogosRequest;
use App\Services\TabelaJogos;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Tabela de jogos do vendedor logado, para impressão (spec 006, FR-010).
 */
class TabelaJogosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        // apostar é só do vendedor: a mesma permissão libera a tabela
        return [self::permissao_cliente('apostas.criar')];
    }

    public function index(TabelaJogosRequest $request, TabelaJogos $tabela): JsonResponse
    {
        return response()->json($tabela->montar($request->user(), $request->filtros()));
    }
}
