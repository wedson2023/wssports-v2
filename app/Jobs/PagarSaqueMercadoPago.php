<?php

namespace App\Jobs;

use App\Models\Saques;
use App\Services\PagamentoSaques;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Saque automático pelo Mercado Pago (spec 007, FR-039). Uma tentativa só: a falha devolve o
 * saque à fila manual do painel, sem pagar em dobro.
 */
class PagarSaqueMercadoPago implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $saque_id) {}

    public function handle(PagamentoSaques $pagamento): void
    {
        $pagamento->enviar(Saques::findOrFail($this->saque_id));
    }
}
