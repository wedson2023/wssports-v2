<?php

namespace App\Models;

use App\Enums\PeriodoJogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurações de quem não está logado (registro único).
 */
class VisitantesConfiguracoes extends Model
{
    use SoftDeletes;

    public const CAMPOS = [
        'esportes_permitidos',
        'apostar_outros_esportes',
        'ao_vivo_habilitado',
        'apostar_jogadores',
        'periodo_jogos',
        'data_travamento_sistema',
        'quantidade_minima_opcoes',
        'quantidade_maxima_opcoes',
        'valor_minimo_aposta',
        'valor_maximo_aposta',
        'odd_minima',
        'premio_maximo',
        'multiplicador',
        'ganho_multiplo_palpites',
        'horas_validade_codigo',
    ];

    protected $table = 'visitantes_configuracoes';

    /**
     * @var list<string>
     */
    protected $fillable = self::CAMPOS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'esportes_permitidos' => 'array',
            'apostar_outros_esportes' => 'boolean',
            'ao_vivo_habilitado' => 'boolean',
            'apostar_jogadores' => 'boolean',
            'periodo_jogos' => PeriodoJogos::class,
            'data_travamento_sistema' => 'datetime',
            'quantidade_minima_opcoes' => 'integer',
            'quantidade_maxima_opcoes' => 'integer',
            'valor_minimo_aposta' => 'decimal:2',
            'valor_maximo_aposta' => 'decimal:2',
            'odd_minima' => 'decimal:2',
            'premio_maximo' => 'decimal:2',
            'multiplicador' => 'integer',
            'ganho_multiplo_palpites' => 'decimal:2',
            'horas_validade_codigo' => 'integer',
        ];
    }

    public static function atual(): self
    {
        return static::query()->firstOrFail();
    }

    /**
     * Esportes exibidos na listagem: só Futebol quando outros esportes estão desligados.
     *
     * @return list<string>
     */
    public function esportes_visiveis(): array
    {
        return $this->apostar_outros_esportes ? array_values($this->esportes_permitidos ?? []) : ['FUTEBOL'];
    }
}
