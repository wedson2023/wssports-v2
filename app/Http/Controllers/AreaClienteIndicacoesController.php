<?php

namespace App\Http\Controllers;

use App\Http\Resources\IndicacoesResource;
use App\Models\Clientes;
use App\Models\ClientesIndicacoes;
use App\Services\Indicacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Área "Minhas indicações" do cliente logado (spec 007, US9).
 */
class AreaClienteIndicacoesController extends Controller
{
    public function index(Request $request, Indicacoes $indicacoes): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'pagina.integer' => 'A página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        /** @var Clientes $cliente */
        $cliente = $request->user('clientes');

        $lista = ClientesIndicacoes::with('indicado')
            ->where('indicador_clientes_id', $cliente->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 30, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        return IndicacoesResource::collection($lista)->additional(['resumo' => $indicacoes->resumo($cliente)]);
    }
}
