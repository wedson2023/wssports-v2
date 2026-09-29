<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginClienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AreaClienteAutenticacaoController extends Controller
{
    /**
     * Autentica o cliente por DDI, telefone e senha e devolve um token JWT do guard clientes.
     */
    public function login(LoginClienteRequest $request): JsonResponse
    {
        $request->garantir_limite_tentativas();

        $token = auth('clientes')->attempt($request->only('ddi', 'telefone', 'password'));

        if (! $token) {
            $request->registrar_tentativa_falha();

            return response()->json(['message' => 'Telefone ou senha inválidos.'], 401);
        }

        if (! auth('clientes')->user()->pode_acessar()) {
            auth('clientes')->logout();

            return response()->json(['message' => 'Cliente sem permissão de acesso.'], 403);
        }

        $request->limpar_tentativas();

        return $this->resposta_token($token);
    }

    /**
     * Gera um novo token e invalida o atual.
     */
    public function refresh(): JsonResponse
    {
        return $this->resposta_token(auth('clientes')->refresh());
    }

    /**
     * Invalida o token atual imediatamente.
     */
    public function logout(): Response
    {
        auth('clientes')->logout();

        return response()->noContent();
    }

    private function resposta_token(string $token): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => auth('clientes')->factory()->getTTL() * 60,
        ]);
    }
}
