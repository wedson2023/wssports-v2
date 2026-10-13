<?php

namespace App\Console\Commands;

use App\Services\GestaoBonus;
use Illuminate\Console\Command;

/**
 * Retira os bônus cujo prazo para cumprir o rollover acabou (spec 007, FR-018b; antigo
 * BonusCommand).
 */
class ExpirarBonusCommand extends Command
{
    protected $signature = 'bonus:expirar';

    protected $description = 'Retira os bônus vencidos que não cumpriram o rollover';

    public function handle(GestaoBonus $bonus): int
    {
        $this->info($bonus->expirar_vencidos().' bônus expirado(s).');

        return self::SUCCESS;
    }
}
