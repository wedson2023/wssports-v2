<?php

namespace App\Console\Commands;

use App\Enums\SituacaoAposta;
use App\Models\Apostas;
use App\Services\DecisaoAoVivo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rede de segurança do delay do ao vivo (FR-035): recusa sem debitar nada as apostas Em análise
 * que passaram do delay mais a margem sem decisão (job que falhou ou worker parado).
 */
class RecusarAnalisesPresasCommand extends Command
{
    /**
     * Margem, em segundos, depois do delay do apostador.
     */
    private const MARGEM_SEGUNDOS = 60;

    protected $signature = 'apostas:recusar_analises_presas';

    protected $description = 'Recusa as apostas do ao vivo que ficaram presas em análise';

    public function handle(DecisaoAoVivo $decisao): int
    {
        $recusadas = 0;

        // candidatas: recebidas há mais que a margem; o delay de cada uma é conferido abaixo
        $candidatas = Apostas::where('situacao', SituacaoAposta::EmAnálise)
            ->where('recebida_em', '<=', now()->subSeconds(self::MARGEM_SEGUNDOS))
            ->pluck('id');

        foreach ($candidatas as $apostas_id) {
            $recusadas += (int) DB::transaction(function () use ($apostas_id, $decisao) {
                $aposta = Apostas::lockForUpdate()->find($apostas_id);

                if ($aposta === null || $aposta->situacao !== SituacaoAposta::EmAnálise
                    || $aposta->recebida_em->copy()->addSeconds($aposta->delay_ao_vivo() + self::MARGEM_SEGUNDOS)->isFuture()) {
                    return false;
                }

                $decisao->recusar($aposta, $aposta->palpites()->get(), 'Não foi possível concluir a análise.');

                return true;
            });
        }

        $this->info("{$recusadas} aposta(s) em análise recusada(s).");

        return self::SUCCESS;
    }
}
