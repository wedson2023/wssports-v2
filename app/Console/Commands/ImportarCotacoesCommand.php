<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExecutarCargaPreJogo;
use App\Services\ImportacaoCotacoes;
use Illuminate\Console\Command;

/**
 * Carga das cotações e dos jogadores do pré-jogo (substitui fonte:cotacao).
 */
class ImportarCotacoesCommand extends Command
{
    use ExecutarCargaPreJogo;

    protected $signature = 'confrontos_cotacoes:importar';

    protected $description = 'Busca no provedor e grava as cotações e os jogadores dos confrontos do pré-jogo';

    public function handle(ImportacaoCotacoes $importacao): int
    {
        return $this->executar_carga(
            'das cotações',
            fn () => $importacao->importar(),
            fn (array $resumo) => "{$resumo['atualizados']} confrontos atualizados, {$resumo['jogadores']} jogadores, {$resumo['ignorados']} confrontos ainda inexistentes",
        );
    }
}
