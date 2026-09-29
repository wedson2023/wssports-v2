<?php

namespace App\Models;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\TipoTransacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Movimentação de um dos saldos do cliente. Nunca é editada nem excluída:
 * correções são feitas por novas transações.
 */
class ClientesTransacoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_transacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clientes_id',
        'carteira',
        'tipo',
        'origem',
        'referencia_id',
        'valor',
        'saldo_anterior',
        'saldo_posterior',
        'usuarios_id',
        'observacao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carteira' => Carteira::class,
            'tipo' => TipoTransacao::class,
            'origem' => OrigemTransacao::class,
            'valor' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Filtros do extrato (período em dias inteiros e carteira), da mais recente para a mais antiga.
     *
     * @param  array{data_inicial?: string, data_final?: string, carteira?: string}  $filtros
     */
    public function scopeExtrato(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(isset($filtros['data_inicial']), fn (Builder $c) => $c->where('created_at', '>=', $filtros['data_inicial'].' 00:00:00'))
            ->when(isset($filtros['data_final']), fn (Builder $c) => $c->where('created_at', '<=', $filtros['data_final'].' 23:59:59'))
            ->when(isset($filtros['carteira']), fn (Builder $c) => $c->where('carteira', $filtros['carteira']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
