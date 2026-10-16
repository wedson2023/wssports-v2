<?php

namespace App\Models;

use App\Enums\Carteira;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sessão de jogo do cliente no cassino (spec 011, data-model seção 6). Os totais são incrementados na
 * mesma transação de cada rodada.
 */
class CassinosSessoes extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_sessoes';

    /**
     * Gravada só pelos serviços do cassino.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carteira' => Carteira::class,
            'bonus_primeiro' => 'boolean',
            'total_apostado' => 'decimal:2',
            'total_ganho' => 'decimal:2',
            'quantidade_rodadas' => 'integer',
            'aberta_em' => 'datetime',
            'ultima_rodada_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(CassinosJogos::class, 'cassinos_jogos_id')->withTrashed();
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CassinosJogosCanais::class, 'cassinos_jogos_canais_id')->withTrashed();
    }

    public function rodadas(): HasMany
    {
        return $this->hasMany(CassinosRodadas::class, 'cassinos_sessoes_id');
    }
}
