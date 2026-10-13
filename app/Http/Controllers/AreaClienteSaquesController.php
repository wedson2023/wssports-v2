<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaquesRequest;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Services\AcessoAssistido;
use App\Services\SolicitacaoSaques;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pedido de saque do cliente logado (spec 007, US3).
 */
class AreaClienteSaquesController extends Controller
{
    public function store(SaquesRequest $request, SolicitacaoSaques $saques): JsonResponse
    {
        /** @var Clientes $cliente */
        $cliente = $request->user('clientes');
        $saque = $saques->solicitar($cliente, $request->valor(), $this->autor($request), $request->ip());

        return response()->json([
            'data' => ['codigo' => $saque->codigo, 'valor' => $saque->valor, 'situacao' => $saque->situacao],
            'message' => 'Saque solicitado com sucesso. O valor estará disponível em até 24 horas.',
        ], 201);
    }

    /**
     * Usuário do painel no acesso assistido (US7); null quando é o próprio cliente.
     */
    private function autor(Request $request): ?Usuarios
    {
        return app(AcessoAssistido::class)->autor($request);
    }
}
