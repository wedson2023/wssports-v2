<?php

namespace App\Models;

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
