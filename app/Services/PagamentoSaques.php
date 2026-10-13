<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\ModoSaque;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoSaque;
use App\Exceptions\GatewayIndisponivelException;
use App\Models\Clientes;
use App\Models\Saques;
use App\Models\Usuarios;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagamento dos saques (spec 007, FR-038 e FR-039, research R-13): baixa manual, recusa com
 * devolução e envio automático pelo Mercado Pago (Payouts). A falha do envio devolve o saque à
 * fila manual, sem devolver o valor.
 */
class PagamentoSaques
{
    /**
     * Tipo da chave Pix no Payouts do Mercado Pago (chave aleatória não tem tipo próprio na
     * documentação; vai como PIX_CODE).
     */
    private const TIPOS_CHAVE = [
        'CPF' => 'CPF',
        'CNPJ' => 'CNPJ',
        'E-mail' => 'EMAIL',
        'Telefone' => 'PHONE',
        'Chave aleatória' => 'PIX_CODE',
    ];

    public function __construct(private SaldoClientes $saldo, private ClienteMercadoPago $mercado_pago) {}

    public function dar_baixa(Saques $saque, Usuarios $usuario): Saques
    {
        return DB::transaction(function () use ($saque, $usuario) {
            $saque = $this->bloquear_pendente($saque);
            $saque->forceFill([
                'situacao' => SituacaoSaque::Pago,
                'decidido_por' => $usuario->id,
                'decidido_em' => now(),
                'pago_em' => now(),
            ])->save();

            return $saque;
        });
    }

    public function recusar(Saques $saque, Usuarios $usuario, string $motivo): Saques
    {
        return DB::transaction(function () use ($saque, $usuario, $motivo) {
            $saque = $this->bloquear_pendente($saque);
            $devolucao = $this->saldo->creditar(
                Clientes::withTrashed()->findOrFail($saque->clientes_id),
                Carteira::Saldo,
                (string) $saque->valor,
                OrigemTransacao::DevoluçãoSaque,
                $usuario,
                $saque->id,
                "Saque recusado: {$motivo}",
            );

            $saque->forceFill([
                'situacao' => SituacaoSaque::Recusado,
                'motivo_recusa' => $motivo,
                'decidido_por' => $usuario->id,
                'decidido_em' => now(),
                'devolucao_clientes_transacoes_id' => $devolucao->id,
            ])->save();

            return $saque;
        });
    }

    /**
     * Envia o Pix pelo Mercado Pago; aprovado no painel (usuario) ou automático (null).
     */
    public function enviar(Saques $saque, ?Usuarios $usuario = null): Saques
    {
        $saque = DB::transaction(function () use ($saque, $usuario) {
            $saque = $this->bloquear_pendente($saque);
            $saque->forceFill([
                'situacao' => SituacaoSaque::Processando,
                'decidido_por' => $usuario?->id ?? $saque->decidido_por,
                'decidido_em' => $usuario ? now() : $saque->decidido_em,
                'motivo_falha' => null,
            ])->save();

            return $saque;
        });

        try {
            $resposta = $this->mercado_pago->pagar_pix_saque([
                'external_reference' => str_replace('-', '', $saque->codigo),
                'config' => ['notification_url' => url('/api/webhooks/mercado-pago')],
                'description' => 'Saque do cliente',
                'transactions' => [[
                    'type' => 'pix',
                    'description' => 'Saque do cliente',
                    'pix' => [
                        'type' => self::TIPOS_CHAVE[$saque->pix_tipo_chave->value] ?? 'PIX_CODE',
                        'chave' => $saque->pix_chave,
                    ],
                    'amount' => ['currency' => 'BRL', 'value' => (float) $saque->valor],
                    'external_reference' => str_replace('-', '', $saque->codigo),
                ]],
            ], $saque->codigo);

            $saque->forceFill(['pagamento_id' => (string) ($resposta['id'] ?? '')])->save();
        } catch (RequestException|GatewayIndisponivelException $erro) {
            $this->voltar_para_fila($saque, $erro instanceof GatewayIndisponivelException
                ? $erro->getMessage()
                : 'O Mercado Pago recusou o pagamento (HTTP '.$erro->response->status().').');
        }

        return $saque->refresh();
    }

    /**
     * Situação das transações do saque no Mercado Pago (aviso ou consulta).
     */
    public function confirmar(Saques $saque): void
    {
        if ($saque->situacao !== SituacaoSaque::Processando || ! $saque->pagamento_id) {
            return;
        }

        $transacoes = $this->mercado_pago->consultar_saque($saque->pagamento_id);
        $status = collect($transacoes['results'] ?? $transacoes['transactions'] ?? $transacoes)->pluck('status')->filter()->first();

        if ($status === 'success') {
            $saque->forceFill(['situacao' => SituacaoSaque::Pago, 'pago_em' => now()])->save();
        } elseif (in_array($status, ['canceled', 'error'], true)) {
            $this->voltar_para_fila($saque, "O Mercado Pago não concluiu o pagamento ({$status}).");
        }
    }

    private function voltar_para_fila(Saques $saque, string $motivo): void
    {
        $saque->forceFill([
            'situacao' => SituacaoSaque::Pendente,
            'modo' => ModoSaque::Manual,
            'motivo_falha' => mb_substr($motivo, 0, 255),
        ])->save();
    }

    private function bloquear_pendente(Saques $saque): Saques
    {
        $saque = Saques::lockForUpdate()->findOrFail($saque->id);

        if ($saque->situacao !== SituacaoSaque::Pendente) {
            throw ValidationException::withMessages(['saque' => 'Este saque já foi decidido.']);
        }

        return $saque;
    }
}
