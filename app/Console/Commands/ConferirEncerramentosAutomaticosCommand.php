<?php

namespace App\Console\Commands;

use App\Services\EncerramentosAutomaticos;
use Illuminate\Console\Command;

/**
 * Confere todos os encerramentos automáticos ativos (spec 010, R-12); cobre as apostas só com jogos
 * do pré-jogo, cujas cotações mudam na carga de 5 minutos.
 */
class ConferirEncerramentosAutomaticosCommand extends Command
{
    protected $signature = 'apostas:conferir_encerramentos_automaticos';

    protected $description = 'Encerra as apostas cujo encerramento automático alcançou o valor-alvo';

    public function handle(EncerramentosAutomaticos $automaticos): int
    {
        $executados = $automaticos->conferir();

        $this->info("Encerramentos automáticos: {$executados} executado(s).");

        return self::SUCCESS;
    }
}
