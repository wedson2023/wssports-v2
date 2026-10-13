<?php

namespace App\Jobs;

use App\Models\NotificacoesMercadoPago;
use App\Services\ClienteMercadoPago;
use App\Services\ProcessamentoDepositos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processa em segundo plano um aviso de pagamento do Mercado Pago (spec 007, research R-10):
 * consulta o pagamento na API e atualiza o depósito.
 */
class ProcessarPagamentoMercadoPago implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $notificacao_id) {}

    public function handle(ClienteMercadoPago $mercado_pago, ProcessamentoDepositos $processamento): void
    {
        $notificacao = NotificacoesMercadoPago::findOrFail($this->notificacao_id);
        $pagamento = $mercado_pago->consultar_pagamento($notificacao->recurso_id);

        $encontrado = $processamento->atualizar($pagamento);

        $notificacao->marcar(
            $encontrado ? NotificacoesMercadoPago::PROCESSADA : NotificacoesMercadoPago::IGNORADA,
            $encontrado ? null : 'pagamento sem depósito no sistema',
        );
    }

    public function failed(Throwable $erro): void
    {
        NotificacoesMercadoPago::find($this->notificacao_id)?->marcar(NotificacoesMercadoPago::FALHOU, mb_substr($erro->getMessage(), 0, 255));
    }
}
