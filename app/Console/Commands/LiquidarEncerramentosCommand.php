<?php

namespace App\Console\Commands;

use App\Services\LiquidacaoEncerramentos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Credita os encerramentos de aposta cuja janela de segurança já passou (spec 010, FR-032).
 */
class LiquidarEncerramentosCommand extends Command
{
    protected $signature = 'apostas:liquidar_encerramentos';

    protected $description = 'Credita no saldo dos clientes os valores dos encerramentos de aposta cuja janela de pagamento já passou';

    public function handle(LiquidacaoEncerramentos $liquidacao): int
    {
        $resumo = $liquidacao->liquidar();
        $mensagem = "Encerramentos: {$resumo['creditados']} creditados, {$resumo['falhas']} falhas.";

        if ($resumo['creditados'] > 0 || $resumo['falhas'] > 0) {
            Log::info($mensagem);
        }

        $this->info($mensagem);

        return self::SUCCESS;
    }
}
