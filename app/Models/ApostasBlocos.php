<?php

namespace App\Models;

use App\Enums\SituacaoBloco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bloco Criar Aposta (spec 008): palpites de um mesmo jogo do pré-jogo com uma cotação combinada,
 * que contam como um jogo na aposta.
 */
class ApostasBlocos extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_blocos';

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
            'cotacao_vista' => 'decimal:2',
            'cotacao' => 'decimal:2',
            'situacao' => SituacaoBloco::class,
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

    public function palpites(): HasMany
    {
        return $this->hasMany(ApostasPalpites::class, 'apostas_blocos_id');
    }

    /**
     * Bloco anulado: fica fora da cotação total (vale 1,00).
     */
    public function e_anulado(): bool
    {
        return $this->situacao === SituacaoBloco::Anulado;
    }
}
