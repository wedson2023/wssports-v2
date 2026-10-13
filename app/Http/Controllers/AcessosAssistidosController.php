<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Models\Clientes;
use App\Services\AcessoAssistido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Painel: gera o endereço para o admin ou supervisor abrir a conta de um cliente (spec 007, US7).
 */
class AcessosAssistidosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('clientes.acessar_conta')];
    }

    public function store(Request $request, Clientes $cliente, AcessoAssistido $acesso): JsonResponse
    {
        return response()->json($acesso->gerar($request->user(), $cliente, $request->ip()), 201);
    }
}
