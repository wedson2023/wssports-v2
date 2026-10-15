<?php

namespace App\Jobs;

use App\Services\EncerramentoApostas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Decide o encerramento Em análise no fim do tempo de confirmação do ao vivo (spec 010, R-09). Roda
 * uma única vez; se falhar, o pedido preso é recusado pelo apostas:recusar_analises_presas.
 */
class DecidirEncerramento implements ShouldQueue
{
    use Queueable;

    /**
     * A mesma fila da decisão das apostas do ao vivo.
     */
    public const FILA = 'apostas';

    public int $tries = 1;

    public function __construct(public int $apostas_encerramentos_id) {}

    public function handle(EncerramentoApostas $encerramento): void
    {
        $encerramento->decidir($this->apostas_encerramentos_id);
    }
}
