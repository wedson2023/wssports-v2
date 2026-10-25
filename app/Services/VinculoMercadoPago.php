<?php

namespace App\Services;

use App\Enums\AcaoConfiguracao;
use App\Enums\SituacaoGateway;
use App\Models\Configuracoes;
use App\Models\GatewaysPagamento;
use App\Models\Usuarios;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vínculo da conta da banca à aplicação do programador por OAuth (spec 007, research R-05): o
 * admin autoriza no Mercado Pago e volta com a conta vinculada, sem criar aplicação.
 */
class VinculoMercadoPago
{
    // renova quando faltam menos dias que isso (o token vale 180 dias)
    private const DIAS_ANTES_DE_RENOVAR = 30;

    private const GATEWAY = 'Mercado Pago';

    public function __construct(private ClienteMercadoPago $mercado_pago, private HistoricoConfiguracoes $historico) {}

    /**
     * Endereço de autorização; o state (uso único, 10 minutos) liga o retorno a este admin.
     */
    public function iniciar(Usuarios $admin): string
    {
        if (! config('services.mercado_pago.client_id') || ! config('services.mercado_pago.client_secret')) {
            throw ValidationException::withMessages(['gateway' => 'Credenciais da aplicação não configuradas no servidor.']);
        }

        $state = Str::random(40);
        Cache::put("mercado_pago.state.{$state}", $admin->id, 600);

        return $this->mercado_pago->url_autorizacao($state);
    }

    /**
     * Retorno da autorização: confere o state, troca o código e grava o vínculo novo.
     *
     * @return array{sucesso: bool, mensagem: string}
     */
    public function concluir(?string $code, ?string $state, ?string $erro): array
    {
        $admin_id = $state ? Cache::pull("mercado_pago.state.{$state}") : null;

        if ($admin_id === null) {
            return ['sucesso' => false, 'mensagem' => 'Pedido de vínculo inválido ou expirado. Tente de novo pelo painel.'];
        }

        if ($erro || ! $code) {
            return ['sucesso' => false, 'mensagem' => 'Vínculo cancelado no Mercado Pago.'];
        }

        try {
            $token = $this->mercado_pago->trocar_codigo($code);
        } catch (RequestException) {
            return ['sucesso' => false, 'mensagem' => 'O Mercado Pago recusou o vínculo. Tente de novo pelo painel.'];
        }

        DB::transaction(function () use ($token, $admin_id) {
            GatewaysPagamento::where('situacao', '!=', SituacaoGateway::Desvinculado)->get()->each(function (GatewaysPagamento $anterior) {
                $anterior->forceFill(['situacao' => SituacaoGateway::Desvinculado, 'access_token' => null, 'refresh_token' => null])->save();
                $anterior->delete();
            });

            GatewaysPagamento::create([
                'gateway' => self::GATEWAY,
                'situacao' => SituacaoGateway::Vinculado,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'conta_id' => (string) ($token['user_id'] ?? ''),
                'expira_em' => now()->addSeconds((int) ($token['expires_in'] ?? 15552000)),
                'vinculado_em' => now(),
                'vinculado_por' => $admin_id,
            ]);

            Configuracoes::atual()->update(['gateway_pagamento' => self::GATEWAY]);

            // histórico da tela Configurações (spec 021): o autor é o admin guardado no state
            $admin = Usuarios::withTrashed()->find($admin_id);

            if ($admin !== null) {
                $this->historico->registrar($admin, 'mercado_pago', AcaoConfiguracao::Vínculo);
            }
        });

        return ['sucesso' => true, 'mensagem' => 'Conta do Mercado Pago vinculada com sucesso.'];
    }

    /**
     * Renova o token perto de vencer; a falha fica registrada e o token atual vale até vencer.
     */
    public function renovar(): ?string
    {
        $gateway = GatewaysPagamento::atual();

        if ($gateway === null || $gateway->expira_em->gt(now()->addDays(self::DIAS_ANTES_DE_RENOVAR))) {
            return null;
        }

        try {
            $token = $this->mercado_pago->renovar_token((string) $gateway->refresh_token);
        } catch (RequestException $erro) {
            $gateway->forceFill([
                'situacao' => SituacaoGateway::FalhaRenovação,
                'falha_renovacao' => 'O Mercado Pago recusou a renovação (HTTP '.$erro->response->status().'). Refaça o vínculo pelo painel.',
            ])->save();
            Log::warning('Renovação do token do Mercado Pago falhou.');

            return 'falhou';
        }

        $gateway->forceFill([
            'situacao' => SituacaoGateway::Vinculado,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $gateway->refresh_token,
            'expira_em' => now()->addSeconds((int) ($token['expires_in'] ?? 15552000)),
            'renovado_em' => now(),
            'falha_renovacao' => null,
        ])->save();

        return 'renovado';
    }

    public function desvincular(): void
    {
        DB::transaction(function () {
            GatewaysPagamento::where('situacao', '!=', SituacaoGateway::Desvinculado)->get()->each(function (GatewaysPagamento $gateway) {
                $gateway->forceFill(['situacao' => SituacaoGateway::Desvinculado, 'access_token' => null, 'refresh_token' => null])->save();
                $gateway->delete();
            });

            Configuracoes::atual()->update(['gateway_pagamento' => null]);
        });
    }
}
