<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opção de uma categoria especial, com a cotação fixa cadastrada pelo administrador.
 */
class EspeciaisOpcoes extends Model
{
    use SoftDeletes;

    protected $table = 'especiais_opcoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'cotacao',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cotacao' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function especial(): BelongsTo
    {
        return $this->belongsTo(Especiais::class, 'especiais_id')->withTrashed();
    }
}
