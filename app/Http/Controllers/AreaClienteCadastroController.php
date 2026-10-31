<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientesRequest;
use App\Http\Resources\ClientesResource;
use App\Services\CadastroClientes;
use Illuminate\Http\JsonResponse;

class AreaClienteCadastroController extends Controller
{
    /**
     * Cadastro público: cria a conta e já devolve o token de acesso do cliente; com a confirmação de
     * cadastro ligada, ele só entra depois de confirmar o e-mail (spec 025, FR-029).
     */
    public function store(StoreClientesRequest $request, CadastroClientes $cadastro): JsonResponse
    {
        $cliente = $cadastro->cadastrar($request->validated());

        if ($cliente->confirmacao_pendente) {
            return response()->json(['cliente' => new ClientesResource($cliente), 'confirmacao_pendente' => true], 201);
        }

        $token = auth('clientes')->login($cliente);

        return response()->json([
            'cliente' => new ClientesResource($cliente),
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => auth('clientes')->factory()->getTTL() * 60,
        ], 201);
    }
}
