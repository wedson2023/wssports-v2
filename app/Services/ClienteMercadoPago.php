<?php

namespace App\Services;

use App\Models\GatewaysPagamento;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Única classe que fala com a API do Mercado Pago (research R-04 a R-13). Credenciais da
 * aplicação vêm do config; o token da conta vinculada, do GatewaysPagamento. Nada de token vai
 * para o log.
 */
class ClienteMercadoPago
{
    /**
     * Endereço de autorização (OAuth) para o admin vincular a conta da banca.
     */
    public function url_autorizacao(string $state): string
    {
        return config('services.mercado_pago.url_autorizacao').'?'.http_build_query([
            'client_id' => config('services.mercado_pago.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $this->url_retorno(),
        ]);
    }

    /**
     * @return array<string, mixed> access_token, refresh_token, expires_in, user_id...
     */
    public function trocar_codigo(string $code): array
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->url_retorno(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function renovar_token(string $refresh_token): array
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
        ]);
    }

    /**
     * Cria um pagamento (Pix ou cartão) na conta vinculada.
     *
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    public function criar_pagamento(array $corpo, string $chave_idempotencia): array
    {
        return $this->enviar('post', '/v1/payments', $corpo, ['X-Idempotency-Key' => $chave_idempotencia]);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultar_pagamento(string $id): array
    {
        return $this->enviar('get', '/v1/payments/'.rawurlencode($id));
    }

    /**
     * Saque automático por Pix (API de Payouts, `POST /v1/payouts`, conferida na documentação em
     * 2026-10-12). Em produção, o corpo é assinado com a chave privada do integrador; sem a chave,
     * a chamada vai como teste.
     *
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    public function pagar_pix_saque(array $corpo, string $chave_idempotencia): array
    {
        $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $chave_privada = config('services.mercado_pago.payouts_chave_privada');
        $cabecalhos = ['X-Idempotency-Key' => $chave_idempotencia];

        if ($chave_privada) {
            openssl_sign($json, $assinatura, $chave_privada, OPENSSL_ALGO_SHA256);
            $cabecalhos['X-enforce-signature'] = 'true';
            $cabecalhos['X-signature'] = base64_encode($assinatura);
        } else {
            $cabecalhos['X-enforce-signature'] = 'false';
            $cabecalhos['X-test-token'] = 'true';
        }

        return $this->enviar('post', '/v1/payouts', $corpo, $cabecalhos, $json);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultar_saque(string $id): array
    {
        return $this->enviar('get', '/v1/payouts/'.rawurlencode($id).'/transactions');
    }

    /**
     * Confere a assinatura da notificação: manifesto "id:{data.id};request-id:{x-request-id};ts:{ts};"
     * com HMAC-SHA256 e a assinatura secreta da aplicação.
     */
    public function assinatura_valida(?string $x_signature, ?string $x_request_id, string $data_id): bool
    {
        $segredo = config('services.mercado_pago.webhook_secret');

        if (! $segredo || ! $x_signature || ! $x_request_id) {
            return false;
        }

        $partes = [];

        foreach (explode(',', $x_signature) as $parte) {
            [$chave, $valor] = array_pad(array_map('trim', explode('=', $parte, 2)), 2, null);
            $partes[$chave] = $valor;
        }

        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }

        // o id que vem na URL é usado em minúsculas quando é alfanumérico (documentação do Mercado Pago)
        $manifesto = 'id:'.mb_strtolower($data_id).';request-id:'.$x_request_id.';ts:'.$partes['ts'].';';

        return hash_equals(hash_hmac('sha256', $manifesto, $segredo), $partes['v1']);
    }

    private function url_retorno(): string
    {
        return url('/mercado-pago/retorno');
    }

    /**
     * @param  array<string, string>  $dados
     * @return array<string, mixed>
     */
    private function token(array $dados): array
    {
        try {
            return $this->cliente()
                ->asForm()
                ->post('/oauth/token', [
                    'client_id' => config('services.mercado_pago.client_id'),
                    'client_secret' => config('services.mercado_pago.client_secret'),
                    ...$dados,
                ])
                ->throw()
                ->json();
        } catch (RequestException $erro) {
            // só o status e a mensagem do Mercado Pago, nunca o corpo enviado (tem o segredo)
            Log::warning('Mercado Pago OAuth falhou', ['status' => $erro->response->status(), 'mensagem' => $erro->response->json('message')]);

            throw $erro;
        }
    }

    /**
     * @param  array<string, mixed>|null  $corpo
     * @param  array<string, string>  $cabecalhos
     * @return array<string, mixed>
     */
    private function enviar(string $metodo, string $caminho, ?array $corpo = null, array $cabecalhos = [], ?string $json = null): array
    {
        $requisicao = $this->cliente()->withToken(GatewaysPagamento::token_atual())->withHeaders($cabecalhos);

        try {
            $resposta = match (true) {
                $metodo === 'get' => $requisicao->get($caminho),
                $json !== null => $requisicao->withBody($json, 'application/json')->post($caminho),
                default => $requisicao->post($caminho, $corpo ?? []),
            };

            return $resposta->throw()->json() ?? [];
        } catch (RequestException $erro) {
            Log::warning('Mercado Pago recusou a chamada', [
                'caminho' => $caminho,
                'status' => $erro->response->status(),
                'mensagem' => $erro->response->json('message'),
                'causa' => $erro->response->json('cause'),
            ]);

            throw $erro;
        }
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl(config('services.mercado_pago.url_api'))->acceptJson()->timeout(30);
    }
}
