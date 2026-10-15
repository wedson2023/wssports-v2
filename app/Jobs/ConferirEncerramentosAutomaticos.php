<?php

namespace App\Jobs;

use App\Models\ApostasEncerramentosAutomaticos;
use App\Services\EncerramentosAutomaticos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Confere os encerramentos automáticos das apostas com os jogos alterados numa carga (spec 010,
 * R-12): a oferta muda com as cotações.
 */
class ConferirEncerramentosAutomaticos implements ShouldQueue
{
    use Queueable;

    public const FILA = 'apostas';

    public int $tries = 1;

    /**
     * @param  list<int>  $confrontos_ids
     */
    public function __construct(public array $confrontos_ids) {}

    public function handle(EncerramentosAutomaticos $automaticos): void
    {
        $automaticos->conferir($this->confrontos_ids);
    }

    /**
     * Despacha a conferência só quando há automático ativo em aposta com algum desses jogos (uma
     * consulta), para a carga não encher a fila à toa.
     *
     * @param  list<int>  $confrontos_ids
     */
    public static function despachar_se_houver(array $confrontos_ids): void
    {
        if ($confrontos_ids === []) {
            return;
        }

        $existe = ApostasEncerramentosAutomaticos::ativos()
            ->whereIn('apostas_id', DB::table('apostas_palpites')->whereIn('confrontos_id', $confrontos_ids)->whereNull('deleted_at')->select('apostas_id'))
            ->exists();

        if ($existe) {
            self::dispatch(array_values($confrontos_ids))->onQueue(self::FILA);
        }
    }
}
