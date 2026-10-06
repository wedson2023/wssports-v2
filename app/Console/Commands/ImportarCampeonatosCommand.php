<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExecutarCargaPreJogo;
use App\Services\ImportacaoCampeonatos;
use Illuminate\Console\Command;

/**
 * Carga dos campeonatos do pré-jogo (substitui fonte:campeonatos).
 */
class ImportarCampeonatosCommand extends Command
{
    use ExecutarCargaPreJogo;

    protected $signature = 'campeonatos:importar';

    protected $description = 'Busca no provedor e grava os campeonatos do pré-jogo';

    public function handle(ImportacaoCampeonatos $importacao): int
    {
        return $this->executar_carga(
            'dos campeonatos',
            fn () => $importacao->importar(),
            fn (array $resumo) => "{$resumo['recebidos']} recebidos, {$resumo['gravados']} novos ou alterados, {$resumo['herancas']} heranças",
        );
    }
}
