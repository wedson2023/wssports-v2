<?php

namespace App\Models;

use App\Enums\Carteira;
use App\Enums\EfeitoAcaoCassino;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lançamento do provedor aceito pela carteira do cassino (spec 011, data-model seção 7). Nunca é
 * editado, exceto a marcação de cancelada.
 */
class CassinosRodadas extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_rodadas';

    /**
     * Gravada só pelos serviços da carteira do cassino.
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
            'efeito' => EfeitoAcaoCassino::class,
            'carteira' => Carteira::class,
            'valor_aposta' => 'decimal:2',
            'valor_premio' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
            'cancelada_em' => 'datetime',
            'dados_provedor' => 'array',
        ];
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(CassinosSessoes::class, 'cassinos_sessoes_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function acao_cassino(): BelongsTo
    {
        return $this->belongsTo(CassinosAcoes::class, 'cassinos_acoes_id')->withTrashed();
    }

    public function transacao(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'clientes_transacoes_id');
    }

    /**
     * Aposta original cancelada ou reembolsada por esta rodada.
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cassinos_rodadas_id');
    }

    public function rollovers(): HasMany
    {
        return $this->hasMany(CassinosRodadasRollovers::class, 'cassinos_rodadas_id');
    }
}
