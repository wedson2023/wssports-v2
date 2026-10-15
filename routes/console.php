<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cargas do pré-jogo: uma por rota do provedor, em segundo plano para não atrasar o ao vivo e
// escalonadas em minutos diferentes, na ordem em que uma depende da outra
// campeonatos a cada 10 min (:00, :10, :20...)
Schedule::command('campeonatos:importar')->cron('*/10 * * * *')->runInBackground()->withoutOverlapping(10);

// confrontos a cada 5 min, 1 min depois (:01, :06, :11...)
Schedule::command('confrontos:importar')->cron('1-59/5 * * * *')->runInBackground()->withoutOverlapping(10);

// cotações e jogadores a cada 5 min, 1 min depois dos confrontos (:02, :07, :12...)
Schedule::command('confrontos_cotacoes:importar')->cron('2-59/5 * * * *')->runInBackground()->withoutOverlapping(10);

// apuração (spec 009): resultados a cada 5 min, 1 min depois das cotações (:03, :08, :13...)
Schedule::command('confrontos_resultados:importar')->cron('3-59/5 * * * *')->runInBackground()->withoutOverlapping(10);

// jogos cancelados ou adiados a cada 5 min, 1 min depois dos resultados (:04, :09, :14...)
Schedule::command('confrontos_cancelados:importar')->cron('4-59/5 * * * *')->runInBackground()->withoutOverlapping(10);

// prêmios: credita os que já passaram da janela de segurança (antigo apostas:premiadas)
Schedule::command('apostas:liquidar_premios')->everyMinute()->withoutOverlapping(5);

// carga do ao vivo a cada 5 segundos; a trava por tempo protege se ela parar
Schedule::command('confrontos_ao_vivo:importar')->everyFiveSeconds()->withoutOverlapping(1);

// conferência do ao vivo com o segundo provedor, em qualquer horário
Schedule::command('confrontos_ao_vivo:conferir')->everyMinute()->runInBackground()->withoutOverlapping(2);

// apostas: expira os códigos de visitante vencidos e recusa as análises do ao vivo presas
Schedule::command('apostas:expirar_pendentes')->everyMinute()->withoutOverlapping(2);
Schedule::command('apostas:recusar_analises_presas')->everyMinute()->withoutOverlapping(2);

// pagamentos (spec 007): renova o token da conta do Mercado Pago perto de vencer
Schedule::command('mercado_pago:renovar_token')->dailyAt('03:00')->withoutOverlapping(10);

// depósitos: expira os Pix vencidos e confere no Mercado Pago os pendentes (cobre aviso perdido)
Schedule::command('depositos:conferir')->everyMinute()->withoutOverlapping(2);

// bônus: retira os vencidos que não cumpriram o rollover (antigo BonusCommand)
Schedule::command('bonus:expirar')->everyTenMinutes()->withoutOverlapping(5);
