<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExecutarCargaPreJogo;
use App\Enums\OrigemApuracao;
use App\Services\ImportacaoResultados;
use Illuminate\Console\Command;

/**
 * Carga dos resultados do pré-jogo e apuração das apostas (spec 009; substitui fonte:resultados).
 */
class ImportarResultadosCommand extends Command
{
    use ExecutarCargaPreJogo;

    protected $signature = 'confrontos_resultados:importar';

    protected $description = 'Busca no provedor os resultados dos jogos do pré-jogo e apura as apostas dos jogos alterados';

    public function handle(ImportacaoResultados $importacao): int
    {
        return $this->executar_carga(
            'dos resultados',
            fn () => $importacao->importar(OrigemApuracao::CargaResultados),
            fn (array $resumo) => "{$resumo['recebidos']} jogos recebidos, {$resumo['alterados']} alterados, {$resumo['apostas']} apostas apuradas, {$resumo['ignorados']} ignorados, {$resumo['falhas']} falhas",
        );
    }
}
