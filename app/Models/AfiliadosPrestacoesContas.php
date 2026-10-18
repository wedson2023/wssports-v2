<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Prestação de contas do afiliado: fecha todos os lançamentos em aberto; valor pago nunca negativo.
 * Criada só pelo serviço PrestacaoContasAfiliados e nunca editada nem excluída.
 */
class AfiliadosPrestacoesContas extends Model
{
    use SoftDeletes;

    protected $table = 'afiliados_prestacoes_contas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afiliados_id',
        'valor_apurado',
        'valor_pago',
        'quantidade_lancamentos',
        'periodo_inicio',
        'periodo_fim',
        'usuarios_id',
        'observacao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_apurado' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'quantidade_lancamentos' => 'integer',
            'periodo_inicio' => 'datetime',
            'periodo_fim' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliados::class, 'afiliados_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(AfiliadosLancamentos::class, 'afiliados_prestacoes_contas_id');
    }
}
