<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Correção de resultado de uma categoria especial encerrada (spec 024): vencedoras, empatadas e
 * divisão antes e depois, quem corrigiu e quantas apostas foram reapuradas.
 */
class EspeciaisCorrecoes extends Model
{
    use SoftDeletes;

    protected $table = 'especiais_correcoes';

    /**
     * Gravado só pelo serviço de encerramento, nunca direto da requisição.
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
            'vencedoras_anteriores' => 'array',
            'empatadas_anteriores' => 'array',
            'dividir_empate_anterior' => 'boolean',
            'vencedoras_novas' => 'array',
            'empatadas_novas' => 'array',
            'dividir_empate_novo' => 'boolean',
            'apostas_afetadas' => 'integer',
        ];
    }

    public function especial(): BelongsTo
    {
        return $this->belongsTo(Especiais::class, 'especiais_id')->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
