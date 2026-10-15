<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExecutarCargaPreJogo;
use App\Enums\OrigemApuracao;
use App\Services\CancelamentoConfrontos;
use Illuminate\Console\Command;

/**
 * Carga dos jogos cancelados ou adiados (spec 009; substitui fonte:cancelados).
 */
class ImportarCanceladosCommand extends Command
{
    use ExecutarCargaPreJogo;

    protected $signature = 'confrontos_cancelados:importar';

    protected $description = 'Busca no provedor os jogos cancelados ou adiados e cancela os palpites deles nas apostas ainda sem resultado';

    public function handle(CancelamentoConfrontos $cancelamento): int
    {
        return $this->executar_carga(
            'dos cancelados',
            fn () => $cancelamento->importar(OrigemApuracao::CargaCancelados),
            fn (array $resumo) => "{$resumo['recebidos']} jogos recebidos, {$resumo['cancelados']} cancelados agora, {$resumo['palpites']} palpites cancelados, {$resumo['ignorados']} ignorados, {$resumo['falhas']} falhas",
        );
    }
}
