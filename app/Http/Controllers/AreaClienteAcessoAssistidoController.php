<?php

namespace App\Http\Controllers;

use App\Http\Requests\IniciarAcessoAssistidoRequest;
use App\Services\AcessoAssistido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Site: troca o código do acesso assistido pelo token e o encerra (spec 007, US7).
 */
class AreaClienteAcessoAssistidoController extends Controller
{
    public function iniciar(IniciarAcessoAssistidoRequest $request, AcessoAssistido $acesso): JsonResponse
    {
        return response()->json($acesso->iniciar($request->validated('codigo'), $request->ip()));
    }

    public function encerrar(Request $request, AcessoAssistido $acesso): Response
    {
        $acesso->encerrar($request);

        return response()->noContent();
    }
}
