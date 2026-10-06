<?php

namespace App\Services;

use App\Enums\SituacaoAoVivo;
use App\Exceptions\FalhaProvedorException;
use App\Models\Configuracoes;
use App\Models\ConfrontosAoVivo;
use Illuminate\Support\Facades\Log;

/**
 * Conferência do ao vivo: sorteia um jogo em andamento e compara o minuto dele com o de um
 * segundo provedor. Sistema atrasado mais de 1 minuto liga a trava geral (todo o ao vivo sai
 * zerado); diferença de volta a 1 minuto ou menos desliga a trava. Sem jogo ou com falha do
 * provedor, nada muda.
 */
class ConferenciaAoVivo
{
    /**
     * Atraso máximo aceito, em minutos, em relação ao segundo provedor.
     */
    private const ATRASO_MAXIMO = 1;

    public function __construct(private ProvedorCotacoes $provedor) {}

    public function conferir(): void
    {
        $configuracoes = Configuracoes::atual();

        $jogo = ConfrontosAoVivo::whereIn('situacao', array_column(SituacaoAoVivo::cases(), 'value'))
            ->where('ultima_atualizacao_em', '>=', now()->subMinutes($configuracoes->minutos_permanencia_ao_vivo))
            ->inRandomOrder()
            ->first();

        if ($jogo === null) {
            return;
        }

        try {
            $minuto_provedor = $this->provedor->consultar_minuto($jogo->codigo_externo);
        } catch (FalhaProvedorException $erro) {
            Log::warning("Conferência do ao vivo sem resultado: {$erro->getMessage()}");

            return;
        }

        $atraso = $minuto_provedor - $jogo->minuto;
        $detalhes = "jogo {$jogo->codigo_externo}, minuto do sistema {$jogo->minuto}, minuto do provedor {$minuto_provedor}";

        if ($atraso > self::ATRASO_MAXIMO && ! $configuracoes->ao_vivo_travado) {
            $configuracoes->forceFill(['ao_vivo_travado' => true, 'ao_vivo_travado_em' => now()])->save();
            Log::warning("Trava geral do ao vivo acionada: {$detalhes}.");
        } elseif ($atraso <= self::ATRASO_MAXIMO && $configuracoes->ao_vivo_travado) {
            $configuracoes->forceFill(['ao_vivo_travado' => false, 'ao_vivo_travado_em' => null])->save();
            Log::info("Trava geral do ao vivo liberada: {$detalhes}.");
        }
    }
}
