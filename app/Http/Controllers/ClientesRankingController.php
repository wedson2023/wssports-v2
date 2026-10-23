<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RankingClientesRequest;
use App\Http\Resources\RankingClientesResource;
use App\Services\RankingClientes;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Aba Ranking da tela Clientes (spec 019, US5): clientes da rede ordenados por depósitos, valor
 * apostado, resultado para a banca ou saques pagos no período.
 */
class ClientesRankingController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly RankingClientes $ranking) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('clientes.ver_ranking')];
    }

    public function __invoke(RankingClientesRequest $request): AnonymousResourceCollection
    {
        [$inicio, $fim] = $request->intervalo();

        $pagina = $this->ranking->pagina($request->user(), $request->criterio(), $inicio, $fim, $request->por_pagina(), $request->pagina())
            ->withPath($request->url())
            ->withQueryString();

        return RankingClientesResource::collection($pagina)->additional(['meta' => [
            'criterio' => $request->criterio(),
            'periodo' => $request->periodo(),
            'inicio' => $inicio->toIso8601String(),
            'fim' => $fim->toIso8601String(),
        ]]);
    }
}
