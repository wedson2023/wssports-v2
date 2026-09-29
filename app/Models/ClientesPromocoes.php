<?php

namespace App\Models;

use App\Enums\CategoriaPromocao;
use App\Enums\ModalidadePromocao;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Enums\TipoGanho;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Promoção (antigo bônus) creditada no saldo promocional de uma modalidade.
 */
class ClientesPromocoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_promocoes';

    /**
     * Mesmos padrões da tabela, para valerem antes de a promoção ser gravada.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativa' => true,
        'rollover' => 0,
    ];

    /**
     * Os campos de estorno ficam de fora: só o estorno os altera.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'descricao',
        'modalidade',
        'categoria',
        'tipo_ganho',
        'valor',
        'rollover',
        'valor_minimo_aposta',
        'valor_maximo_aposta',
        'valor_maximo_deposito',
        'valor_maximo_conversao',
        'odd_minima_aposta_simples',
        'odd_minima_aposta_multipla',
        'data_inicio',
        'data_fim',
        'ativa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modalidade' => ModalidadePromocao::class,
            'categoria' => CategoriaPromocao::class,
            'tipo_ganho' => TipoGanho::class,
            'estorno_situacao' => SituacaoEstorno::class,
            'valor' => 'decimal:2',
            'rollover' => 'integer',
            'valor_minimo_aposta' => 'decimal:2',
            'valor_maximo_aposta' => 'decimal:2',
            'valor_maximo_deposito' => 'decimal:2',
            'valor_maximo_conversao' => 'decimal:2',
            'odd_minima_aposta_simples' => 'decimal:2',
            'odd_minima_aposta_multipla' => 'decimal:2',
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'ativa' => 'boolean',
            'estorno_iniciado_em' => 'datetime',
            'estorno_concluido_em' => 'datetime',
            'estorno_total_clientes' => 'integer',
            'estorno_clientes_processados' => 'integer',
            'estorno_valor_total' => 'decimal:2',
        ];
    }

    public function autor_estorno(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'estorno_usuarios_id');
    }

    /**
     * Promoções ativas, não estornadas e dentro do período.
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where('ativa', true)
            ->whereNull('estorno_situacao')
            ->where('data_inicio', '<=', now())
            ->where(fn (Builder $fim) => $fim->whereNull('data_fim')->orWhere('data_fim', '>=', now()));
    }

    public function vigente(): bool
    {
        return $this->ativa
            && ! $this->estornada()
            && $this->data_inicio->lte(now())
            && ($this->data_fim === null || $this->data_fim->gte(now()));
    }

    /**
     * Já foi creditada a algum cliente.
     */
    public function aplicada(): bool
    {
        return ClientesTransacoes::where('origem', OrigemTransacao::Promoção)
            ->where('referencia_id', $this->id)
            ->exists();
    }

    /**
     * Tem estorno em andamento ou concluído.
     */
    public function estornada(): bool
    {
        return $this->estorno_situacao !== null;
    }
}
