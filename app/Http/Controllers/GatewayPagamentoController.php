<?php

namespace App\Http\Controllers;

use App\Enums\AcaoConfiguracao;
use App\Enums\Funcao;
use App\Enums\SituacaoGateway;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Models\GatewaysPagamento;
use App\Services\HistoricoConfiguracoes;
use App\Services\VinculoMercadoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Campo "Gateway de pagamentos" das configurações do painel (spec 007, US8): situação, início do
 * vínculo por OAuth e desvínculo. A situação é vista por Admin e Supervisor (spec 021,
 * Clarification 1); vincular e desvincular, só o Admin.
 */
class GatewayPagamentoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('configuracoes.editar', only: ['show']),
            self::permissao_cliente('gateway_pagamento.vincular', except: ['show']),
        ];
    }

    /**
     * Situação do vínculo, sem os tokens, e se quem vê pode alterá-lo.
     */
    public function show(Request $request): JsonResponse
    {
        $gateway = GatewaysPagamento::atual();

        return response()->json(['data' => [
            'gateway' => $gateway?->gateway,
            'situacao' => $gateway?->situacao ?? SituacaoGateway::Desvinculado,
            'vinculado_em' => $gateway?->vinculado_em,
            'expira_em' => $gateway?->expira_em,
            'renovado_em' => $gateway?->renovado_em,
            'falha_renovacao' => $gateway?->falha_renovacao,
            'pode_alterar' => Funcao::usuario_pode($request->user(), 'gateway_pagamento.vincular'),
        ]]);
    }

    /**
     * Endereço de autorização do Mercado Pago, aberto pelo painel em nova aba.
     */
    public function autorizar(Request $request, VinculoMercadoPago $vinculo): JsonResponse
    {
        return response()->json(['url' => $vinculo->iniciar($request->user())]);
    }

    public function destroy(Request $request, VinculoMercadoPago $vinculo, HistoricoConfiguracoes $historico): Response
    {
        $vinculo->desvincular();
        $historico->registrar($request->user(), 'mercado_pago', AcaoConfiguracao::Desvínculo);

        return response()->noContent();
    }
}
