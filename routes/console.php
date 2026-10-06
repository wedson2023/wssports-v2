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

// carga do ao vivo a cada 5 segundos; a trava por tempo protege se ela parar
Schedule::command('confrontos_ao_vivo:importar')->everyFiveSeconds()->withoutOverlapping(1);

// conferência do ao vivo com o segundo provedor, em qualquer horário
Schedule::command('confrontos_ao_vivo:conferir')->everyMinute()->runInBackground()->withoutOverlapping(2);
