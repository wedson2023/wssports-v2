<?php

namespace App\Jobs;

use App\Services\DecisaoAoVivo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Decide a aposta do ao vivo no fim do delay (R-01). Roda uma única vez; se falhar, a aposta presa
 * é recusada pelo comando apostas:recusar_analises_presas.
 */
class DecidirApostaAoVivo implements ShouldQueue
{
    use Queueable;

    /**
     * Fila própria, para que jobs lentos de outras features não atrasem a decisão.
     */
    public const FILA = 'apostas';

    public int $tries = 1;

    public function __construct(public int $apostas_id) {}

    public function handle(DecisaoAoVivo $decisao): void
    {
        $decisao->decidir($this->apostas_id);
    }
}
