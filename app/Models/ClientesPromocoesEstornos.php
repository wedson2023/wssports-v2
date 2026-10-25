<?php

namespace App\Models;

use App\Enums\TipoEstornoBonus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estorno do bônus de um cliente (spec 020, R-04): um por crédito de promoção, com o tipo, o autor, o
 * motivo e quanto saiu do saldo promocional.
 */
class ClientesPromocoesEstornos extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_promocoes_estornos';

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
            'tipo' => TipoEstornoBonus::class,
            'valor_retirado' => 'decimal:2',
        ];
    }

    public function promocao(): BelongsTo
    {
        return $this->belongsTo(ClientesPromocoes::class, 'clientes_promocoes_id')->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    /**
     * Crédito do bônus estornado.
     */
    public function credito(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'clientes_transacoes_id');
    }

    public function rollover(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'clientes_rollovers_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function debito(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'debito_clientes_transacoes_id');
    }
}
