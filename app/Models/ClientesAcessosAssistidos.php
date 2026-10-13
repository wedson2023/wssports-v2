<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acesso de um usuário do painel à conta de um cliente (spec 007, data-model §5).
 */
class ClientesAcessosAssistidos extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_acessos_assistidos';

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
            'codigo_expira_em' => 'datetime',
            'iniciado_em' => 'datetime',
            'expira_em' => 'datetime',
            'encerrado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Iniciado, não encerrado e dentro do prazo.
     */
    public function ativo(): bool
    {
        return $this->iniciado_em !== null && $this->encerrado_em === null && $this->expira_em?->isFuture();
    }
}
