<?php

namespace App\Models;

use App\Enums\TipoNotificacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aviso enviado a um cliente; a chave única por cliente impede o envio repetido (spec 010,
 * data-model seção 4).
 */
class ClientesNotificacoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_notificacoes';

    /**
     * Gravado só pelo serviço NotificacoesPush.
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
            'tipo' => TipoNotificacao::class,
            'aparelhos' => 'integer',
            'falhas' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(ConfrontosEventos::class, 'confrontos_eventos_id');
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Apostas::class, 'apostas_id');
    }
}
