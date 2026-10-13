<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoGateway;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Models\GatewaysPagamento;
use App\Services\VinculoMercadoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Campo "Gateway de pagamentos" das configurações do painel (spec 007, US8): situação, início do
 * vínculo por OAuth e desvínculo. Só o Admin.
 */
class GatewayPagamentoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('gateway_pagamento.vincular')];
    }

    /**
     * Situação do vínculo, sem os tokens.
     */
    public function show(): JsonResponse
    {
        $gateway = GatewaysPagamento::atual();

        return response()->json(['data' => [
            'gateway' => $gateway?->gateway,
            'situacao' => $gateway?->situacao ?? SituacaoGateway::Desvinculado,
            'vinculado_em' => $gateway?->vinculado_em,
            'expira_em' => $gateway?->expira_em,
            'renovado_em' => $gateway?->renovado_em,
            'falha_renovacao' => $gateway?->falha_renovacao,
        ]]);
    }

    /**
     * Endereço de autorização do Mercado Pago, aberto pelo painel em nova aba.
     */
    public function autorizar(Request $request, VinculoMercadoPago $vinculo): JsonResponse
    {
        return response()->json(['url' => $vinculo->iniciar($request->user())]);
    }

    public function destroy(VinculoMercadoPago $vinculo): Response
    {
        $vinculo->desvincular();

        return response()->noContent();
    }
}
