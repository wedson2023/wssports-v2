<?php

namespace App\Models;

use App\Enums\ModoSaque;
use App\Enums\SituacaoSaque;
use App\Enums\TipoChavePix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Saque do cliente (spec 007, data-model §2).
 */
class Saques extends Model
{
    use SoftDeletes;

    protected $table = 'saques';

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
            'pix_tipo_chave' => TipoChavePix::class,
            'modo' => ModoSaque::class,
            'situacao' => SituacaoSaque::class,
            'decidido_em' => 'datetime',
            'pago_em' => 'datetime',
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

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'devolucao_clientes_transacoes_id');
    }

    public function decidido_por_usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'decidido_por');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
