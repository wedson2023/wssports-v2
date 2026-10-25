<?php

namespace App\Http\Controllers;

use App\Enums\TipoEstornoBonus;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\BonusRecebidosFiltrosRequest;
use App\Http\Requests\EstornarBonusRequest;
use App\Http\Resources\BonusRecebidosResource;
use App\Services\EstornoBonus;
use App\Services\ListagemBonusRecebidos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Aba Bônus recebidos da tela Bônus (spec 020, FR-020 a FR-027): os bônus dados aos clientes da rede,
 * os cartões, o detalhe e o estorno do bônus de um cliente só (Admin e Supervisor).
 */
class ClientesBonusController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly ListagemBonusRecebidos $listagem) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes_promocoes.gerenciar', only: ['index', 'resumo', 'show']),
            self::permissao_cliente('clientes_promocoes.estornar', only: ['estornar']),
        ];
    }

    public function index(BonusRecebidosFiltrosRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $bonus = $this->listagem->consulta($request->user(), $filtros)
            ->paginate($filtros['por_pagina'] ?? 30)
            ->withQueryString();

        return BonusRecebidosResource::collection($bonus);
    }

    /**
     * Cartões da aba: em andamento, creditado, convertido e retirado (FR-024).
     */
    public function resumo(BonusRecebidosFiltrosRequest $request): JsonResponse
    {
        return response()->json($this->listagem->resumo($request->user(), $request->validated()));
    }

    public function show(Request $request, int $bonus): BonusRecebidosResource
    {
        return $this->detalhe($request, $bonus);
    }

    /**
     * Estorna o bônus só deste cliente, com motivo e senha (FR-027).
     */
    public function estornar(EstornarBonusRequest $request, int $bonus, EstornoBonus $estorno): BonusRecebidosResource
    {
        $credito = $this->listagem->credito($request->user(), $bonus);
        $estorno->estornar($credito, TipoEstornoBonus::Individual, $request->user(), $request->validated('motivo'));

        return $this->detalhe($request, $bonus);
    }

    private function detalhe(Request $request, int $bonus): BonusRecebidosResource
    {
        $resource = new BonusRecebidosResource($this->listagem->detalhe($request->user(), $bonus));
        $resource->detalhe = true;

        return $resource;
    }
}
