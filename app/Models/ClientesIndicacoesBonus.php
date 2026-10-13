<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bônus de indicação pago por um grupo completo de indicações válidas (spec 007, data-model §7).
 */
class ClientesIndicacoesBonus extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_indicacoes_bonus';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cancelado_em' => 'datetime'];
    }

    public function rollover(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'clientes_rollovers_id');
    }

    public function indicacoes(): HasMany
    {
        return $this->hasMany(ClientesIndicacoes::class, 'clientes_indicacoes_bonus_id');
    }
}
