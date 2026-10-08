<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quanto uma aposta somou num rollover do cliente (desfeito no cancelamento).
 */
class ApostasRollovers extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_rollovers';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'desfeito_em' => 'datetime',
        ];
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Apostas::class, 'apostas_id');
    }

    public function rollover(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'clientes_rollovers_id');
    }
}
