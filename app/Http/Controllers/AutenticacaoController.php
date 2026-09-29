<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AutenticacaoController extends Controller
{
    /**
     * Autentica por login e senha e devolve um token JWT.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->garantir_limite_tentativas();

        $token = auth('api')->attempt($request->only('login', 'password'));

        if (! $token) {
            $request->registrar_tentativa_falha();

            return response()->json(['message' => 'Login ou senha inválidos.'], 401);
        }

        if (! auth('api')->user()->pode_acessar()) {
            auth('api')->logout();

            return response()->json(['message' => 'Usuário sem permissão de acesso.'], 403);
        }

        $request->limpar_tentativas();

        return $this->resposta_token($token);
    }

    /**
     * Gera um novo token e invalida o atual.
     */
    public function refresh(): JsonResponse
    {
        return $this->resposta_token(auth('api')->refresh());
    }

    /**
     * Invalida o token atual imediatamente.
     */
    public function logout(): Response
    {
        auth('api')->logout();

        return response()->noContent();
    }

    private function resposta_token(string $token): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
