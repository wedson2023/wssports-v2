<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesMeiosPagamentoRequest;
use App\Http\Resources\ClientesMeiosPagamentoResource;
use App\Models\Clientes;
use App\Models\ClientesMeiosPagamento;
use App\Services\MeiosPagamentoClientes;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Meios de pagamento de um cliente, pelo painel (rotas aninhadas e escopadas ao cliente).
 */
class ClientesMeiosPagamentoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private MeiosPagamentoClientes $meios) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.listar', only: ['index']),
            self::permissao_cliente('clientes.editar', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Clientes $cliente): AnonymousResourceCollection
    {
        return ClientesMeiosPagamentoResource::collection(
            $cliente->meios_pagamento()->orderByDesc('principal')->oldest('id')->get()
        );
    }

    public function store(ClientesMeiosPagamentoRequest $request, Clientes $cliente): ClientesMeiosPagamentoResource
    {
        return new ClientesMeiosPagamentoResource($this->meios->cadastrar($cliente, $request->validated()));
    }

    public function update(ClientesMeiosPagamentoRequest $request, Clientes $cliente, ClientesMeiosPagamento $meio_pagamento): ClientesMeiosPagamentoResource
    {
        $this->garantir_do_cliente($cliente, $meio_pagamento);

        return new ClientesMeiosPagamentoResource($this->meios->atualizar($meio_pagamento, $request->validated()));
    }

    public function destroy(Clientes $cliente, ClientesMeiosPagamento $meio_pagamento): Response
    {
        $this->garantir_do_cliente($cliente, $meio_pagamento);

        $this->meios->excluir($meio_pagamento);

        return response()->noContent();
    }

    /**
     * Meio que não pertence ao cliente da rota é tratado como inexistente.
     */
    private function garantir_do_cliente(Clientes $cliente, ClientesMeiosPagamento $meio): void
    {
        abort_unless($meio->clientes_id === $cliente->id, 404, 'Meio de pagamento não encontrado.');
    }
}
