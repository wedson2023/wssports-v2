<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CaixaPeriodoRequest;
use App\Services\CaixaClientes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Caixa clientes do painel (spec 017, US4, contracts/api.md §4): totais do período e a lista por
 * cliente. Só Admin e Supervisor (banca toda), mesmo que outro nível receba a permissão (FR-026).
 */
class CaixaClientesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly CaixaClientes $caixa) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('caixa.ver_clientes'),
        ];
    }

    public function resumo(CaixaPeriodoRequest $request): JsonResponse
    {
        $this->garantir_gestor_geral($request);
        [$inicio, $fim] = $request->intervalo();

        return response()->json($this->caixa->resumo($inicio, $fim));
    }

    public function index(CaixaPeriodoRequest $request): JsonResponse
    {
        $this->garantir_gestor_geral($request);
        [$inicio, $fim] = $request->intervalo();

        $clientes = $this->caixa->clientes(
            $inicio,
            $fim,
            $request->validated('busca'),
            $request->validated('ordenar') ?? 'nome',
            $request->validated('direcao') ?? 'asc',
            $request->por_pagina(),
            max(1, (int) ($request->validated('page') ?? 1)),
        );

        $clientes->withPath($request->url())->withQueryString();

        return response()->json($clientes->toArray());
    }

    private function garantir_gestor_geral(Request $request): void
    {
        abort_unless(in_array($request->user()->funcao(), [Funcao::Admin, Funcao::Supervisor], true), 403, 'Você não tem permissão para esta ação.');
    }
}
