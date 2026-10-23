<?php

namespace App\Models;

use App\Enums\PublicoNotificacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Envio manual de notificação push pelo painel (spec 019, FR-031 a FR-033a): público, texto e
 * quantos clientes foram alcançados; entregues e falhas são somados durante o envio.
 */
class ClientesNotificacoesEnvios extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_notificacoes_envios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuarios_id',
        'publico',
        'clientes_ids',
        'dias_sem_interagir',
        'titulo',
        'mensagem',
        'alcancados',
        'entregues',
        'falhas',
        'concluido_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publico' => PublicoNotificacao::class,
            'clientes_ids' => 'array',
            'dias_sem_interagir' => 'integer',
            'alcancados' => 'integer',
            'entregues' => 'integer',
            'falhas' => 'integer',
            'concluido_em' => 'datetime',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(ClientesNotificacoes::class, 'clientes_notificacoes_envios_id');
    }
}
