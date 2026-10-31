<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Link de confirmação de e-mail do cliente (spec 025, data-model): só o hash do token fica no banco.
 */
class ClientesConfirmacoesEmail extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_confirmacoes_email';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clientes_id',
        'token',
        'expira_em',
        'usado_em',
        'invalidado_em',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
            'invalidado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    public function valido(): bool
    {
        return $this->usado_em === null
            && $this->invalidado_em === null
            && $this->expira_em->isFuture();
    }
}
