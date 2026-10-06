<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Teto de cotação por código (registro único), igual para todos os públicos.
 */
class ConfrontosTetoCotacoes extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_teto_cotacoes';

    /**
     * @var list<string>
     */
    protected $fillable = ['tetos'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tetos' => 'array',
        ];
    }

    public static function atual(): self
    {
        return static::query()->firstOrFail();
    }
}
