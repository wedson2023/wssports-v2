<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Indicação de um cliente por outro (spec 007, data-model §6).
 */
class ClientesIndicacoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_indicacoes';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['validada_em' => 'datetime'];
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'indicador_clientes_id');
    }

    public function indicado(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'indicado_clientes_id')->withTrashed();
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(ClientesIndicacoesBonus::class, 'clientes_indicacoes_bonus_id');
    }
}
