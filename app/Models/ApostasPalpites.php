<?php

namespace App\Models;

use App\Enums\SituacaoPalpite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Palpite de uma aposta: um confronto, um código de cotação e as cotações vista, original e final.
 */
class ApostasPalpites extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_palpites';

    /**
     * Todos os campos são definidos pelos serviços de aposta, nunca direto da requisição.
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
            'situacao' => SituacaoPalpite::class,
            'cotacao_vista' => 'decimal:2',
            'cotacao_original' => 'decimal:2',
            'cotacao_final' => 'decimal:2',
            'dados_ao_vivo_envio' => 'array',
            'dados_ao_vivo_decisao' => 'array',
            'cancelado_em' => 'datetime',
            'restaurado_em' => 'datetime',
        ];
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Apostas::class, 'apostas_id');
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id')->withTrashed();
    }

    public function confronto_ao_vivo(): BelongsTo
    {
        return $this->belongsTo(ConfrontosAoVivo::class, 'confrontos_ao_vivo_id')->withTrashed();
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonatos::class, 'campeonatos_id')->withTrashed();
    }

    public function jogador(): BelongsTo
    {
        return $this->belongsTo(ConfrontosJogadores::class, 'confrontos_jogadores_id')->withTrashed();
    }

    public function e_ao_vivo(): bool
    {
        return $this->confrontos_ao_vivo_id !== null;
    }
}
