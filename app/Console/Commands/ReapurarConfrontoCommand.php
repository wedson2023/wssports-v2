<?php

namespace App\Console\Commands;

use App\Enums\OrigemApuracao;
use App\Models\Confrontos;
use App\Services\ApuracaoApostas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reapura à mão as apostas de um jogo (spec 009, FR-042), com o resultado já gravado, mesmo sem
 * mudança de resultado. Sem mudança real, nada é gravado nem movimentado.
 */
class ReapurarConfrontoCommand extends Command
{
    protected $signature = 'apostas:reapurar {confronto : id do jogo (confrontos.id)}';

    protected $description = 'Reapura as apostas de um jogo com o resultado já gravado';

    public function handle(ApuracaoApostas $apuracao): int
    {
        $confronto = Confrontos::find((int) $this->argument('confronto'));

        if ($confronto === null) {
            $this->error('Jogo não encontrado.');

            return self::FAILURE;
        }

        $resumo = $apuracao->apurar_confronto($confronto, OrigemApuracao::ReprocessamentoManual);
        $mensagem = "Reapuração do jogo {$confronto->id}: {$resumo['apuradas']} apostas reapuradas, {$resumo['alteradas']} alteradas, {$resumo['falhas']} falhas.";

        Log::info($mensagem);
        $this->info($mensagem);

        return $resumo['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
