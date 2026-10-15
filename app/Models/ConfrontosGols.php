<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gol de um jogo do pré-jogo, com o autor e a ordem no jogo (spec 009, R-02).
 */
class ConfrontosGols extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_gols';

    /**
     * Gravado só pela importação dos resultados, nunca direto da requisição.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id')->withTrashed();
    }
}
