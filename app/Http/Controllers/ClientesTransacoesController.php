<?php

namespace App\Http\Controllers;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\TipoTransacao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesTransacoesRequest;
use App\Http\Requests\Concerns\FiltrosExtrato;
use App\Http\Resources\ClientesTransacoesResource;
use App\Models\Clientes;
use App\Services\SaldoClientes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Extrato e movimentação manual de saldo pelo painel.
 */
class ClientesTransacoesController extends Controller implements HasMiddleware
{
    use FiltrosExtrato, GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.listar', only: ['index']),
            self::permissao_cliente('clientes.movimentar_saldo', only: ['store']),
        ];
    }

    public function index(Request $request, Clientes $cliente): AnonymousResourceCollection
    {
        $filtros = $this->validar_filtros_extrato($request);

        $transacoes = $cliente->transacoes()
            ->with('autor')
            ->extrato($filtros)
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ClientesTransacoesResource::collection($transacoes);
    }

    /**
     * Ajuste manual: o autor é o usuário logado e o motivo vira a observação.
     */
    public function store(ClientesTransacoesRequest $request, Clientes $cliente, SaldoClientes $saldo): ClientesTransacoesResource
    {
        $carteira = Carteira::from($request->validated('carteira'));
        $metodo = TipoTransacao::from($request->validated('tipo')) === TipoTransacao::Crédito ? 'creditar' : 'debitar';

        $transacao = $saldo->{$metodo}(
            $cliente,
            $carteira,
            (string) $request->validated('valor'),
            OrigemTransacao::AjusteManual,
            $request->user(),
            observacao: $request->validated('observacao'),
        );

        return new ClientesTransacoesResource($transacao->load('autor'));
    }
}
