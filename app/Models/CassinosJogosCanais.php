<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O jogo num canal (label) do provedor do cassino (spec 011, data-model seção 3, R-06).
 */
class CassinosJogosCanais extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_jogos_canais';

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
            'pre_pago' => 'boolean',
            'bonus_primeiro' => 'boolean',
            'prioridade' => 'integer',
            'ativo' => 'boolean',
            'demo' => 'boolean',
            'flash' => 'boolean',
            'indisponivel_em' => 'datetime',
            'removido_em' => 'datetime',
            'dados_provedor' => 'array',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(CassinosJogos::class, 'cassinos_jogos_id');
    }

    /**
     * Canais que podem ser tentados na abertura: liberados, sem Flash, ainda na lista do provedor e
     * sem recusa recente do provedor (R-08).
     */
    public function scopeAbriveis(Builder $consulta): Builder
    {
        $limite = now()->subHours((int) config('cassino.horas_indisponibilidade_canal'));

        return $consulta
            ->where('cassinos_jogos_canais.ativo', true)
            ->where('cassinos_jogos_canais.flash', false)
            ->whereNull('cassinos_jogos_canais.removido_em')
            ->where(fn (Builder $c) => $c->whereNull('cassinos_jogos_canais.indisponivel_em')
                ->orWhere('cassinos_jogos_canais.indisponivel_em', '<', $limite));
    }

    public function scopePorPrioridade(Builder $consulta): Builder
    {
        return $consulta->orderBy('prioridade')->orderBy('id');
    }
}
