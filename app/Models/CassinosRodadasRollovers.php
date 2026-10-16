<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quanto a aposta de uma rodada do cassino somou num rollover do cliente (desfeito no cancelamento),
 * no padrão de ApostasRollovers (spec 011, R-18).
 */
class CassinosRodadasRollovers extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_rodadas_rollovers';

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

    public function rodada(): BelongsTo
    {
        return $this->belongsTo(CassinosRodadas::class, 'cassinos_rodadas_id');
    }

    public function rollover(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'clientes_rollovers_id');
    }
}
