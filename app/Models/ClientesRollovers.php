<?php

namespace App\Models;

use App\Enums\Carteira;
use App\Enums\TipoRollover;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Valor que o cliente precisa apostar por causa de um crédito: depósito (1 vez) ou bônus (o
 * rollover da promoção), com as regras de uso do bônus gravadas.
 */
class ClientesRollovers extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_rollovers';

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
            'tipo' => TipoRollover::class,
            'carteira' => Carteira::class,
            'valor_creditado' => 'decimal:2',
            'vezes' => 'integer',
            'valor_exigido' => 'decimal:2',
            'valor_apostado' => 'decimal:2',
            'valor_minimo_aposta' => 'decimal:2',
            'valor_maximo_aposta' => 'decimal:2',
            'odd_minima_aposta_simples' => 'decimal:2',
            'odd_minima_aposta_multipla' => 'decimal:2',
            'cumprido_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    /**
     * Rollovers ainda não cumpridos nem cancelados, do mais antigo para o mais novo.
     */
    public function scopePendentes(Builder $consulta): Builder
    {
        return $consulta->whereNull('cumprido_em')->whereNull('cancelado_em')->orderBy('id');
    }
}
