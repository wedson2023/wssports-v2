<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlterarSenhaClienteRequest;
use App\Http\Requests\Concerns\FiltrosExtrato;
use App\Http\Requests\UpdateMeusDadosRequest;
use App\Http\Resources\ClientesResource;
use App\Http\Resources\ClientesTransacoesResource;
use App\Models\Clientes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * "Meus dados" do cliente logado: consulta, edição, troca de senha e extrato.
 */
class AreaClienteMeusDadosController extends Controller
{
    use FiltrosExtrato;

    public function show(Request $request): ClientesResource
    {
        return new ClientesResource($this->cliente($request)->load('configuracoes'));
    }

    /**
     * Nome, gênero e e-mail ficam no cliente; aceita_promocao fica nas configurações.
     */
    public function update(UpdateMeusDadosRequest $request): ClientesResource
    {
        $cliente = $this->cliente($request);

        DB::transaction(function () use ($cliente, $request) {
            $cliente->update($request->safe()->except('aceita_promocao'));

            if ($request->has('aceita_promocao')) {
                $cliente->configuracoes()->update(['aceita_promocao' => $request->boolean('aceita_promocao')]);
            }
        });

        return new ClientesResource($cliente->load('configuracoes'));
    }

    /**
     * Troca a senha e derruba todos os tokens emitidos antes, inclusive o atual.
     */
    public function alterar_senha(AlterarSenhaClienteRequest $request): Response
    {
        $cliente = $this->cliente($request);

        $cliente->update(['password' => $request->validated('password')]);
        $cliente->invalidar_tokens();

        return response()->noContent();
    }

    public function extrato(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->validar_filtros_extrato($request);

        $transacoes = $this->cliente($request)->transacoes()
            ->extrato($filtros)
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ClientesTransacoesResource::collection($transacoes);
    }

    private function cliente(Request $request): Clientes
    {
        return $request->user('clientes');
    }
}
