<?php

namespace App\Models;

use App\Enums\TipoLancamentoCaixa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Suprimento ou retirada no caixa de um vendedor (spec 017). Fica aberto até entrar numa prestação
 * de contas; só os abertos podem ser excluídos (soft delete com quem excluiu).
 */
class CaixaLancamentos extends Model
{
    use SoftDeletes;

    protected $table = 'caixa_lancamentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuarios_id',
        'tipo',
        'valor',
        'ocorrido_em',
        'observacao',
        'lancado_por',
        'caixa_prestacoes_contas_id',
        'excluido_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoLancamentoCaixa::class,
            'valor' => 'decimal:2',
            'ocorrido_em' => 'datetime',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'lancado_por')->withTrashed();
    }

    public function excluido_por_usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'excluido_por')->withTrashed();
    }

    public function prestacao(): BelongsTo
    {
        return $this->belongsTo(CaixaPrestacoesContas::class, 'caixa_prestacoes_contas_id')->withTrashed();
    }

    /**
     * Lançamentos ainda não incluídos numa prestação de contas.
     */
    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereNull('caixa_prestacoes_contas_id');
    }
}
