<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientesRequest;
use App\Http\Resources\ClientesResource;
use App\Services\CadastroClientes;
use Illuminate\Http\JsonResponse;

class AreaClienteCadastroController extends Controller
{
    /**
     * Cadastro público: cria a conta e já devolve o token de acesso do cliente.
     */
    public function store(StoreClientesRequest $request, CadastroClientes $cadastro): JsonResponse
    {
        $cliente = $cadastro->cadastrar($request->validated());

        $token = auth('clientes')->login($cliente);

        return response()->json([
            'cliente' => new ClientesResource($cliente),
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => auth('clientes')->factory()->getTTL() * 60,
        ], 201);
    }
}
