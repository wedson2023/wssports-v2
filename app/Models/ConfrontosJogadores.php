<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cotação de um jogador num confronto (antiga "atletas"). Jogador que deixa de vir na carga
 * recebe exclusão lógica e é restaurado se voltar.
 */
class ConfrontosJogadores extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_jogadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'confrontos_id',
        'codigo_externo',
        'nome',
        'opcao',
        'tipo',
        'odd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo_externo' => 'integer',
            'odd' => 'decimal:2',
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id');
    }
}
