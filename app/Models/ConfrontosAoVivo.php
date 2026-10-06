<?php

namespace App\Models;

use App\Enums\SituacaoAoVivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jogo em andamento, atualizado pela carga do ao vivo a cada 5 segundos.
 */
class ConfrontosAoVivo extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_ao_vivo';

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo_externo' => 'integer',
            'situacao' => SituacaoAoVivo::class,
            'data_inicio' => 'datetime',
            'placar_casa' => 'integer',
            'placar_fora' => 'integer',
            'minuto' => 'integer',
            'quantidade_cotacoes' => 'integer',
            'cotacoes' => 'array',
            'ultima_atualizacao_em' => 'datetime',
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id');
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonatos::class, 'campeonatos_id');
    }

    /**
     * Travado pela trava geral (conferência) ou por estar sem atualização há mais tempo que o
     * limite: a trava vale pela data, sem depender de gravar zeros.
     */
    public function travado(Configuracoes $configuracoes): bool
    {
        return $configuracoes->ao_vivo_travado
            || $this->ultima_atualizacao_em === null
            || $this->ultima_atualizacao_em->lt(now()->subSeconds($configuracoes->segundos_trava_ao_vivo));
    }
}
