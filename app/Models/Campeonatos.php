<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Campeonato vindo do provedor ou cadastrado à mão (manual).
 */
class Campeonatos extends Model
{
    use SoftDeletes;

    protected $table = 'campeonatos';

    /**
     * codigo_externo, ativo, favorito e manual ficam de fora: só a carga e as rotas próprias os
     * alteram.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'pais',
        'bandeira',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo_externo' => 'integer',
            'ativo' => 'boolean',
            'favorito' => 'boolean',
            'manual' => 'boolean',
        ];
    }

    /**
     * Códigos externos dos campeonatos do provedor desativados, enviados ao provedor para que
     * os jogos deles não retornem; manuais nunca são enviados.
     *
     * @return list<int>
     */
    public static function codigos_desativados(): array
    {
        return self::where('ativo', false)
            ->where('manual', false)
            ->whereNotNull('codigo_externo')
            ->pluck('codigo_externo')
            ->all();
    }

    public function confrontos(): HasMany
    {
        return $this->hasMany(Confrontos::class, 'campeonatos_id');
    }

    public function porcentagens(): HasMany
    {
        return $this->hasMany(PorcentagensCampeonatos::class, 'campeonatos_id');
    }

    public function nao_permitidos(): HasMany
    {
        return $this->hasMany(CampeonatosNaoPermitidos::class, 'campeonatos_id');
    }
}
