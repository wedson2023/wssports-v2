<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Antecipação de pendentes de um cliente pelo painel (spec 019, FR-019): quantos prêmios e
 * encerramentos foram creditados antes da janela de segurança, quanto e por quem.
 */
class ClientesAntecipacoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_antecipacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clientes_id',
        'usuarios_id',
        'quantidade_premios',
        'valor_premios',
        'quantidade_encerramentos',
        'valor_encerramentos',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade_premios' => 'integer',
            'valor_premios' => 'decimal:2',
            'quantidade_encerramentos' => 'integer',
            'valor_encerramentos' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
