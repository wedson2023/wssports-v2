<?php

namespace App\Console\Commands;

use App\Enums\ResultadoChamadaCassino;
use App\Models\CassinosChamadas;
use App\Models\Configuracoes;
use Illuminate\Console\Command;

/**
 * Retenção do registro de chamadas do provedor do cassino (spec 011, R-15). Exclusão física
 * justificada no plano: é um log técnico de alto volume (uma linha por giro), sem valor de negócio
 * depois da conferência; o dinheiro fica em cassinos_rodadas e clientes_transacoes, nunca apagados.
 */
class LimparChamadasCassinoCommand extends Command
{
    protected $signature = 'cassinos_chamadas:limpar';

    protected $description = 'Apaga as chamadas antigas do provedor do cassino';

    private const LOTE = 5000;

    public function handle(): int
    {
        $aceitas = $this->apagar(
            [ResultadoChamadaCassino::Aceita, ResultadoChamadaCassino::Repetida],
            now()->subDays(Configuracoes::atual()->dias_registro_chamadas_cassino),
        );
        $recusadas = $this->apagar(
            [ResultadoChamadaCassino::Recusada, ResultadoChamadaCassino::Erro],
            now()->subDays((int) config('cassino.dias_retencao_recusadas')),
        );

        $this->info("Chamadas apagadas: {$aceitas} aceitas ou repetidas e {$recusadas} recusadas ou com erro.");

        return self::SUCCESS;
    }

    /**
     * Apaga em lotes, para não travar a tabela.
     *
     * @param  list<ResultadoChamadaCassino>  $resultados
     */
    private function apagar(array $resultados, \DateTimeInterface $antes_de): int
    {
        $total = 0;

        do {
            $apagadas = CassinosChamadas::withTrashed()
                ->whereIn('resultado', $resultados)
                ->where('created_at', '<', $antes_de)
                ->limit(self::LOTE)
                ->forceDelete();
            $total += $apagadas;
        } while ($apagadas === self::LOTE);

        return $total;
    }
}
