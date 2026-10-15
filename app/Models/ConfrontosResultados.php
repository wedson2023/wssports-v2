<?php

namespace App\Models;

use App\Enums\SituacaoConfronto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Resultado de um jogo do pré-jogo recebido do provedor ou lançado à mão (spec 009, R-01).
 */
class ConfrontosResultados extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_resultados';

    /**
     * Gravado só pela importação dos resultados, nunca direto da requisição.
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
            'placar_casa' => 'integer',
            'placar_fora' => 'integer',
            'gols_primeiro_tempo_casa' => 'integer',
            'gols_primeiro_tempo_fora' => 'integer',
            'gols_segundo_tempo_casa' => 'integer',
            'gols_segundo_tempo_fora' => 'integer',
            'escanteios_casa' => 'integer',
            'escanteios_fora' => 'integer',
            'situacao' => SituacaoConfronto::class,
            'recebido_em' => 'datetime',
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id')->withTrashed();
    }

    /**
     * Só jogo Encerrado com o placar dos dois times é apurado (R-03).
     */
    public function apuravel(): bool
    {
        return $this->situacao === SituacaoConfronto::Encerrado && $this->placar_casa !== null && $this->placar_fora !== null;
    }
}
