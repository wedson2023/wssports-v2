<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações gerais do sistema (registro único; antiga "configs").
 */
class Configuracoes extends Model
{
    use SoftDeletes;

    protected $table = 'configuracoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'somente_cassino',
        'permitir_entrada_campeonatos',
        'sorteio_ambas_marcam_minimo',
        'sorteio_ambas_marcam_maximo',
        'sorteio_ambas_nao_marcam_minimo',
        'sorteio_ambas_nao_marcam_maximo',
        'ao_vivo_habilitado',
        'segundos_trava_ao_vivo',
        'minutos_permanencia_ao_vivo',
        'minuto_limite_ao_vivo',
        'cotacao_maxima_ao_vivo',
        'ao_vivo_travado',
        'ao_vivo_travado_em',
        'nome_sistema',
        'mensagem_bilhete',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'somente_cassino' => 'boolean',
            'permitir_entrada_campeonatos' => 'boolean',
            'sorteio_ambas_marcam_minimo' => 'decimal:2',
            'sorteio_ambas_marcam_maximo' => 'decimal:2',
            'sorteio_ambas_nao_marcam_minimo' => 'decimal:2',
            'sorteio_ambas_nao_marcam_maximo' => 'decimal:2',
            'ao_vivo_habilitado' => 'boolean',
            'segundos_trava_ao_vivo' => 'integer',
            'minutos_permanencia_ao_vivo' => 'integer',
            'minuto_limite_ao_vivo' => 'integer',
            'cotacao_maxima_ao_vivo' => 'decimal:2',
            'ao_vivo_travado' => 'boolean',
            'ao_vivo_travado_em' => 'datetime',
        ];
    }

    public static function atual(): self
    {
        return static::query()->firstOrFail();
    }

    /**
     * Intervalo de sorteio (mínimo e máximo) de odd4 ou odd7; null quando o código não tem
     * sorteio ou o intervalo é inválido (mínimo maior que o máximo).
     *
     * @return array{0: float, 1: float}|null
     */
    public function intervalo_sorteio(string $codigo): ?array
    {
        [$minimo, $maximo] = match ($codigo) {
            'odd4' => [$this->sorteio_ambas_marcam_minimo, $this->sorteio_ambas_marcam_maximo],
            'odd7' => [$this->sorteio_ambas_nao_marcam_minimo, $this->sorteio_ambas_nao_marcam_maximo],
            default => [null, null],
        };

        if ($minimo === null || $maximo === null || (float) $minimo <= 0 || (float) $minimo > (float) $maximo) {
            return null;
        }

        return [(float) $minimo, (float) $maximo];
    }
}
