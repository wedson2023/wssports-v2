<?php

namespace App\Models;

use App\Enums\EventoLancamentoAfiliado;
use App\Enums\TipoGanhoAfiliado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lançamento de comissão do afiliado, gravado no momento do movimento do cliente pelo serviço
 * ComissoesAfiliados. Nunca é editado nem excluído; a prestação de contas só o liga a ela.
 */
class AfiliadosLancamentos extends Model
{
    use SoftDeletes;

    protected $table = 'afiliados_lancamentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afiliados_id',
        'clientes_id',
        'evento',
        'depositos_id',
        'saques_id',
        'chave_evento',
        'tipo_ganho',
        'base',
        'percentual',
        'valor',
        'ocorrido_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoLancamentoAfiliado::class,
            'tipo_ganho' => TipoGanhoAfiliado::class,
            'base' => 'decimal:2',
            'percentual' => 'decimal:2',
            'valor' => 'decimal:2',
            'ocorrido_em' => 'datetime',
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliados::class, 'afiliados_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function prestacao(): BelongsTo
    {
        return $this->belongsTo(AfiliadosPrestacoesContas::class, 'afiliados_prestacoes_contas_id');
    }

    /**
     * Lançamentos ainda não fechados por uma prestação de contas.
     */
    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereNull('afiliados_prestacoes_contas_id');
    }
}
