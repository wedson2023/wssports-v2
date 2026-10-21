<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Prestação de contas do caixa de um vendedor (spec 017): fecha o caixa até `periodo_fim`. Criada só
 * pelo serviço PrestacaoContasCaixa e nunca editada; desfazer a última é um soft delete com quem
 * desfez e o motivo.
 */
class CaixaPrestacoesContas extends Model
{
    use SoftDeletes;

    protected $table = 'caixa_prestacoes_contas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuarios_id',
        'periodo_inicio',
        'periodo_fim',
        'saldo_anterior',
        'valor_apurado',
        'valor_recebido',
        'diferenca',
        'lote',
        'prestado_por',
        'observacao',
        'desfeita_por',
        'motivo_desfazer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'datetime',
            'periodo_fim' => 'datetime',
            'saldo_anterior' => 'decimal:2',
            'valor_apurado' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
            'diferenca' => 'decimal:2',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'prestado_por')->withTrashed();
    }

    public function desfeita_por_usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'desfeita_por')->withTrashed();
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(CaixaLancamentos::class, 'caixa_prestacoes_contas_id');
    }
}
