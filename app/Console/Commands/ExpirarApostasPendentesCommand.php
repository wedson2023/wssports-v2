<?php

namespace App\Console\Commands;

use App\Enums\SituacaoAposta;
use App\Models\Apostas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expira os códigos de visitante que passaram da validade ou que têm algum jogo já iniciado
 * (FR-041; substitui o PendentesCommand do sistema antigo, que apagava as pendentes).
 */
class ExpirarApostasPendentesCommand extends Command
{
    protected $signature = 'apostas:expirar_pendentes';

    protected $description = 'Marca como Expiradas as apostas Pendentes vencidas ou com jogo iniciado';

    public function handle(): int
    {
        $agora = now();

        // atualização condicional: não disputa com uma validação simultânea (que trava a linha)
        $expiradas = Apostas::where('situacao', SituacaoAposta::Pendente)
            ->where(fn ($consulta) => $consulta->where('expira_em', '<=', $agora)
                ->orWhereExists(fn ($iniciado) => $iniciado->select(DB::raw(1))
                    ->from('apostas_palpites as ap')
                    ->join('confrontos as co', 'co.id', '=', 'ap.confrontos_id')
                    ->whereColumn('ap.apostas_id', 'apostas.id')
                    ->whereNull('ap.deleted_at')
                    ->where('co.data_inicio', '<=', $agora)))
            ->update([
                'situacao' => SituacaoAposta::Expirada,
                'motivo_recusa' => 'Código expirado.',
                'updated_at' => $agora,
            ]);

        $this->info("{$expiradas} aposta(s) pendente(s) expirada(s).");

        return self::SUCCESS;
    }
}
