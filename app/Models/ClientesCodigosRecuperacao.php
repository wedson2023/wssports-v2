<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Código temporário de recuperação de senha (guardado em hash).
 */
class ClientesCodigosRecuperacao extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_codigos_recuperacao';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clientes_id',
        'codigo',
        'tentativas',
        'expira_em',
        'usado_em',
        'invalidado_em',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'codigo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tentativas' => 'integer',
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
            'invalidado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    /**
     * Ainda não usado, não invalidado e dentro da validade.
     */
    public function valido(): bool
    {
        return $this->usado_em === null
            && $this->invalidado_em === null
            && $this->expira_em->isFuture();
    }
}
