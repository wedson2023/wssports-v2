<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExecutarCargaPreJogo;
use App\Services\ImportacaoConfrontos;
use Illuminate\Console\Command;

/**
 * Carga dos confrontos do pré-jogo (substitui fonte:confrontos).
 */
class ImportarConfrontosCommand extends Command
{
    use ExecutarCargaPreJogo;

    protected $signature = 'confrontos:importar';

    protected $description = 'Busca no provedor e grava os confrontos do pré-jogo (times, situação e horário)';

    public function handle(ImportacaoConfrontos $importacao): int
    {
        return $this->executar_carga(
            'dos confrontos',
            fn () => $importacao->importar(),
            fn (array $resumo) => "{$resumo['recebidos']} recebidos, {$resumo['gravados']} gravados, {$resumo['ignorados']} sem campeonato",
        );
    }
}
