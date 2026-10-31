<?php

namespace App\Console\Commands;

use App\Services\RoboWhatsapp;
use Illuminate\Console\Command;

/**
 * Confere a conexão do robô (spec 025, research R-08): cobre o aviso automático perdido e o servidor
 * da Evolution fora do ar, para a queda ser percebida em até 5 minutos.
 */
class ConferirRoboWhatsappCommand extends Command
{
    protected $signature = 'robo_whatsapp:conferir';

    protected $description = 'Confere a conexão do robô do WhatsApp e alerta o Admin se ele caiu';

    public function handle(RoboWhatsapp $robo): int
    {
        $robo->conferir();

        return self::SUCCESS;
    }
}
