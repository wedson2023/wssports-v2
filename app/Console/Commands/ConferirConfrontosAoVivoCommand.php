<?php

namespace App\Console\Commands;

use App\Models\Configuracoes;
use App\Services\ConferenciaAoVivo;
use Illuminate\Console\Command;

/**
 * Conferência do ao vivo com um segundo provedor, a cada minuto, sem janela de horário
 * (substitui comparar:aovivo).
 */
class ConferirConfrontosAoVivoCommand extends Command
{
    protected $signature = 'confrontos_ao_vivo:conferir';

    protected $description = 'Confere o minuto de um jogo do ao vivo com o segundo provedor e liga ou desliga a trava geral';

    public function handle(ConferenciaAoVivo $conferencia): int
    {
        $configuracoes = Configuracoes::atual();

        if ($configuracoes->somente_cassino || ! $configuracoes->ao_vivo_habilitado) {
            $this->info('Ao vivo desligado ou sistema só para cassino: conferência não executada.');

            return self::SUCCESS;
        }

        $conferencia->conferir();

        $this->info(Configuracoes::atual()->ao_vivo_travado ? 'Ao vivo travado.' : 'Ao vivo liberado.');

        return self::SUCCESS;
    }
}
