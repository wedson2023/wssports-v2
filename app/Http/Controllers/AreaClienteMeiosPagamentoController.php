<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientesMeiosPagamentoRequest;
use App\Http\Resources\ClientesMeiosPagamentoResource;
use App\Models\ClientesMeiosPagamento;
use App\Services\MeiosPagamentoClientes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Meios de pagamento do cliente logado. Meio de outro cliente é tratado como inexistente.
 */
class AreaClienteMeiosPagamentoController extends Controller
{
    public function __construct(private MeiosPagamentoClientes $meios) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ClientesMeiosPagamentoResource::collection(
            $request->user('clientes')->meios_pagamento()->orderByDesc('principal')->oldest('id')->get()
        );
    }

    public function store(ClientesMeiosPagamentoRequest $request): ClientesMeiosPagamentoResource
    {
        return new ClientesMeiosPagamentoResource($this->meios->cadastrar($request->user('clientes'), $request->validated()));
    }

    public function show(Request $request, ClientesMeiosPagamento $meio_pagamento): ClientesMeiosPagamentoResource
    {
        $this->garantir_dono($request, $meio_pagamento);

        return new ClientesMeiosPagamentoResource($meio_pagamento);
    }

    public function update(ClientesMeiosPagamentoRequest $request, ClientesMeiosPagamento $meio_pagamento): ClientesMeiosPagamentoResource
    {
        $this->garantir_dono($request, $meio_pagamento);

        return new ClientesMeiosPagamentoResource($this->meios->atualizar($meio_pagamento, $request->validated()));
    }

    public function destroy(Request $request, ClientesMeiosPagamento $meio_pagamento): Response
    {
        $this->garantir_dono($request, $meio_pagamento);

        $this->meios->excluir($meio_pagamento);

        return response()->noContent();
    }

    private function garantir_dono(Request $request, ClientesMeiosPagamento $meio): void
    {
        abort_unless($meio->clientes_id === $request->user('clientes')->id, 404, 'Meio de pagamento não encontrado.');
    }
}
