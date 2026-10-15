<?php

namespace App\Jobs;

use App\Models\ClientesNotificacoes;
use App\Services\NotificacoesPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envia um aviso push já registrado (spec 010, R-13). Uma tentativa só: aviso de jogo atrasado não
 * tem valor.
 */
class EnviarNotificacaoPush implements ShouldQueue
{
    use Queueable;

    /**
     * Fila própria, para os avisos não atrasarem a decisão das apostas.
     */
    public const FILA = 'notificacoes';

    public int $tries = 1;

    public function __construct(public int $clientes_notificacoes_id) {}

    public function handle(NotificacoesPush $notificacoes): void
    {
        $notificacao = ClientesNotificacoes::find($this->clientes_notificacoes_id);

        if ($notificacao !== null) {
            $notificacoes->enviar($notificacao);
        }
    }
}
