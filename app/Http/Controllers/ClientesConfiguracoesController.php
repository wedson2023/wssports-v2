<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesConfiguracoesRequest;
use App\Http\Resources\ClientesConfiguracoesResource;
use App\Models\Clientes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações de aposta e saque de um cliente, pelo painel.
 */
class ClientesConfiguracoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.listar', only: ['show']),
            self::permissao_cliente('clientes.editar_configuracoes', only: ['update']),
        ];
    }

    public function show(Clientes $cliente): ClientesConfiguracoesResource
    {
        return new ClientesConfiguracoesResource($cliente->configuracoes);
    }

    public function update(ClientesConfiguracoesRequest $request, Clientes $cliente): ClientesConfiguracoesResource
    {
        $cliente->configuracoes->update($request->validated());

        return new ClientesConfiguracoesResource($cliente->configuracoes);
    }
}
