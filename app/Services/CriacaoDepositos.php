<?php

namespace App\Services;

use App\Enums\MeioDeposito;
use App\Enums\SituacaoDeposito;
use App\Exceptions\GatewayIndisponivelException;
use App\Models\Clientes;
use App\Models\Configuracoes;
use App\Models\Depositos;
use App\Models\GatewaysPagamento;
use App\Models\Usuarios;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Criação do depósito no Mercado Pago (spec 007, FR-019 a FR-027, research R-06 a R-08):
 * validações, comissão da conta master e cobrança Pix ou cartão.
 */
class CriacaoDepositos
{
    /**
     * Atalhos de valor do modal, quando cabem nos limites.
     */
    private const VALORES_RAPIDOS = ['20.00', '50.00', '100.00', '200.00'];

    public function __construct(
        private ClienteMercadoPago $mercado_pago,
        private ProcessamentoDepositos $processamento,
        private MensagensMercadoPago $mensagens,
    ) {}

    /**
     * O que o modal de depósito precisa saber (contracts/api.md §4).
     *
     * @return array<string, mixed>
     */
    public function opcoes(Clientes $cliente): array
    {
        $configuracoes = Configuracoes::atual();
        $disponivel = $configuracoes->depositos_habilitados && GatewaysPagamento::atual() !== null;
        $minimo = (string) $configuracoes->deposito_minimo;
        $maximo = (string) $configuracoes->deposito_maximo;

        return [
            'disponivel' => $disponivel,
            'motivo' => $disponivel ? null : (new GatewayIndisponivelException)->getMessage(),
            'minimo' => $minimo,
            'maximo' => $maximo,
            'valores_rapidos' => array_values(array_filter(
                self::VALORES_RAPIDOS,
                fn (string $valor) => bccomp($valor, $minimo, 2) >= 0 && bccomp($valor, $maximo, 2) <= 0,
            )),
            'meios' => $configuracoes->meios_habilitados(),
            'parcelas_maximas' => $configuracoes->parcelas_maximas,
            'public_key' => config('services.mercado_pago.public_key'),
            'aceita_bonus' => (bool) $cliente->configuracoes?->aceita_promocao,
            'falta_no_cadastro' => $this->falta_no_cadastro($cliente),
        ];
    }

    public function criar_pix(Clientes $cliente, string $valor, bool $aceita_bonus, ?Usuarios $autor, ?string $ip): Depositos
    {
        $configuracoes = Configuracoes::atual();
        $deposito = $this->preparar($cliente, $configuracoes, MeioDeposito::Pix, $valor, $aceita_bonus, $autor, $ip);
        $deposito->expira_em = now()->addMinutes($configuracoes->minutos_expiracao_pix);
        $deposito->save();

        $pagamento = $this->enviar($deposito, [
            'payment_method_id' => 'pix',
            'date_of_expiration' => $deposito->expira_em->copy()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s.vP'),
        ], 'Não foi possível gerar o Pix. Tente de novo.');

        $deposito->forceFill([
            'pagamento_id' => (string) $pagamento['id'],
            'pix_copia_cola' => $pagamento['point_of_interaction']['transaction_data']['qr_code'] ?? null,
            'pix_qr_code' => $pagamento['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
        ])->save();

        return $deposito;
    }

    /**
     * Cartão de crédito: o token vem do MercadoPago.js no navegador; o número do cartão nunca
     * passa pelo servidor (FR-024). Aprovado, já processa; em análise, aguarda o aviso.
     *
     * @param  array{token: string, parcelas: int, payment_method_id: string, issuer_id?: ?string}  $cartao
     */
    public function criar_cartao(Clientes $cliente, string $valor, bool $aceita_bonus, array $cartao, ?Usuarios $autor, ?string $ip): Depositos
    {
        $configuracoes = Configuracoes::atual();

        if ((int) $cartao['parcelas'] > $configuracoes->parcelas_maximas) {
            throw ValidationException::withMessages(['cartao.parcelas' => "O parcelamento máximo é de {$configuracoes->parcelas_maximas}x."]);
        }

        $deposito = $this->preparar($cliente, $configuracoes, MeioDeposito::CartãoCrédito, $valor, $aceita_bonus, $autor, $ip);
        $deposito->parcelas = (int) $cartao['parcelas'];
        $deposito->save();

        $pagamento = $this->enviar($deposito, array_filter([
            'token' => $cartao['token'],
            'installments' => (int) $cartao['parcelas'],
            'payment_method_id' => $cartao['payment_method_id'],
            'issuer_id' => $cartao['issuer_id'] ?? null,
            'statement_descriptor' => Str::limit(Str::ascii((string) $configuracoes->nome_sistema), 13, ''),
        ], fn ($valor) => $valor !== null && $valor !== ''), 'Não foi possível processar o cartão. Tente de novo.');

        $deposito->forceFill([
            'pagamento_id' => (string) $pagamento['id'],
            'valor_total_pago' => $pagamento['transaction_details']['total_paid_amount'] ?? null,
            'status_detalhe' => $pagamento['status_detail'] ?? null,
        ])->save();

        if (($pagamento['status'] ?? null) === 'rejected') {
            $deposito->forceFill(['situacao' => SituacaoDeposito::Recusado])->save();

            throw ValidationException::withMessages(['cartao' => $this->mensagens->recusa($pagamento['status_detail'] ?? null)]);
        }

        $this->processamento->atualizar($pagamento);

        return $deposito->refresh();
    }

    /**
     * Validações comuns e o depósito Pendente com a comissão calculada.
     */
    private function preparar(Clientes $cliente, Configuracoes $configuracoes, MeioDeposito $meio, string $valor, bool $aceita_bonus, ?Usuarios $autor, ?string $ip): Depositos
    {
        if (! $configuracoes->depositos_habilitados || GatewaysPagamento::atual() === null) {
            throw ValidationException::withMessages(['valor' => (new GatewayIndisponivelException)->getMessage()]);
        }

        if (! in_array($meio->value, $configuracoes->meios_habilitados(), true)) {
            throw ValidationException::withMessages(['meio' => 'Meio de pagamento indisponível.']);
        }

        $faltando = $this->falta_no_cadastro($cliente);

        if ($faltando !== []) {
            throw self::cadastro_incompleto('Informe seu CPF e e-mail para depositar.', $faltando);
        }

        if (bccomp($valor, (string) $configuracoes->deposito_minimo, 2) < 0) {
            throw ValidationException::withMessages(['valor' => 'O depósito mínimo é de '.RegrasAposta::reais((string) $configuracoes->deposito_minimo).'.']);
        }

        if (bccomp($valor, (string) $configuracoes->deposito_maximo, 2) > 0) {
            throw ValidationException::withMessages(['valor' => 'O depósito máximo é de '.RegrasAposta::reais((string) $configuracoes->deposito_maximo).'.']);
        }

        $percentual = (string) $configuracoes->percentual_comissao_gateway;
        $comissao = EscolhaPromocoes::percentual($valor, $percentual);

        // a comissão precisa sobrar do valor (depósito muito baixo)
        if (bccomp($comissao, $valor, 2) >= 0) {
            throw ValidationException::withMessages(['valor' => 'O depósito mínimo é de '.RegrasAposta::reais((string) $configuracoes->deposito_minimo).'.']);
        }

        // a escolha de aceitar bônus fica no cliente (FR-021)
        $cliente->configuracoes()->update(['aceita_promocao' => $aceita_bonus]);

        return new Depositos([
            'codigo' => (string) Str::uuid(),
            'clientes_id' => $cliente->id,
            'meio' => $meio,
            'valor' => $valor,
            'comissao' => $comissao,
            'percentual_comissao' => $percentual,
            'aceita_bonus' => $aceita_bonus,
            'situacao' => SituacaoDeposito::Pendente,
            'usuarios_id' => $autor?->id,
            'ip' => $ip,
        ]);
    }

    /**
     * Cria o pagamento no Mercado Pago; falha da API cancela o depósito.
     *
     * @param  array<string, mixed>  $meio
     * @return array<string, mixed>
     */
    private function enviar(Depositos $deposito, array $meio, string $mensagem_falha): array
    {
        $cliente = $deposito->cliente;
        $nomes = preg_split('/\s+/', trim($cliente->nome), 2);

        try {
            return $this->mercado_pago->criar_pagamento([
                'transaction_amount' => (float) $deposito->valor,
                'description' => 'Depósito de crédito na plataforma',
                'external_reference' => $deposito->codigo,
                'notification_url' => url('/api/webhooks/mercado-pago'),
                'application_fee' => (float) $deposito->comissao,
                'payer' => [
                    'email' => $cliente->email,
                    'first_name' => $nomes[0] ?? '',
                    'last_name' => $nomes[1] ?? '',
                    'identification' => ['type' => 'CPF', 'number' => $cliente->cpf],
                ],
                ...$meio,
            ], $deposito->codigo);
        } catch (RequestException|GatewayIndisponivelException $erro) {
            $deposito->forceFill(['situacao' => SituacaoDeposito::Cancelado])->save();

            throw ValidationException::withMessages(['valor' => $erro instanceof GatewayIndisponivelException ? $erro->getMessage() : $mensagem_falha]);
        }
    }

    /**
     * 422 com a mensagem e a lista do que falta ("faltando"), para a tela abrir Meus dados e
     * retomar a operação depois (FR-020, FR-036).
     *
     * @param  list<string>  $faltando
     */
    public static function cadastro_incompleto(string $mensagem, array $faltando): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $mensagem,
            'errors' => ['cadastro' => [$mensagem]],
            'faltando' => $faltando,
        ], 422));
    }

    /**
     * Dados do cadastro que faltam para depositar (FR-020).
     *
     * @return list<string>
     */
    private function falta_no_cadastro(Clientes $cliente): array
    {
        return array_keys(array_filter(['cpf' => ! $cliente->cpf, 'email' => ! $cliente->email]));
    }
}
