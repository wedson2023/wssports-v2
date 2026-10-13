<?php

namespace App\Models;

use App\Enums\MeioDeposito;
use App\Enums\SituacaoDeposito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Depósito do cliente pelo Mercado Pago (spec 007, data-model §1).
 */
class Depositos extends Model
{
    use SoftDeletes;

    protected $table = 'depositos';

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
            'meio' => MeioDeposito::class,
            'situacao' => SituacaoDeposito::class,
            'valor' => 'decimal:2',
            'parcelas' => 'integer',
            'valor_total_pago' => 'decimal:2',
            'comissao' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'aceita_bonus' => 'boolean',
            'expira_em' => 'datetime',
            'pago_em' => 'datetime',
            'valor_estornado' => 'decimal:2',
            'estornado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function transacao(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'clientes_transacoes_id');
    }

    public function promocao(): BelongsTo
    {
        return $this->belongsTo(ClientesPromocoes::class, 'clientes_promocoes_id')->withTrashed();
    }

    public function rollover_bonus(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'bonus_clientes_rollovers_id');
    }

    public function rollover_deposito(): BelongsTo
    {
        return $this->belongsTo(ClientesRollovers::class, 'rollover_clientes_rollovers_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
