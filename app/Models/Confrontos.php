<?php

namespace App\Models;

use App\Enums\SituacaoConfronto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jogo do pré-jogo, vindo do provedor ou cadastrado à mão (manual).
 */
class Confrontos extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos';

    /**
     * codigo_externo, ativo, manual e as marcações de sorteio ficam de fora: só a carga e as
     * rotas próprias os alteram.
     *
     * @var list<string>
     */
    protected $fillable = [
        'campeonatos_id',
        'time_casa',
        'escudo_casa',
        'time_fora',
        'escudo_fora',
        'esporte',
        'situacao',
        'data_inicio',
        'cotacoes',
        'quantidade_cotacoes',
        'limite_valor_apostado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo_externo' => 'integer',
            'situacao' => SituacaoConfronto::class,
            'data_inicio' => 'datetime',
            'ativo' => 'boolean',
            'manual' => 'boolean',
            'odd4_sorteada' => 'boolean',
            'odd7_sorteada' => 'boolean',
            'quantidade_cotacoes' => 'integer',
            'cotacoes' => 'array',
            'limite_valor_apostado' => 'decimal:2',
        ];
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonatos::class, 'campeonatos_id');
    }

    public function jogadores(): HasMany
    {
        return $this->hasMany(ConfrontosJogadores::class, 'confrontos_id');
    }

    public function ao_vivo(): HasOne
    {
        return $this->hasOne(ConfrontosAoVivo::class, 'confrontos_id');
    }

    public function nao_permitidos(): HasMany
    {
        return $this->hasMany(ConfrontosNaoPermitidos::class, 'confrontos_id');
    }

    public function nao_permitidos_ao_vivo(): HasMany
    {
        return $this->hasMany(ConfrontosAoVivoNaoPermitidos::class, 'confrontos_id');
    }

    /**
     * Cotação do provedor para o código; 0 quando ausente (indisponível).
     */
    public function cotacao(string $codigo): float
    {
        return (float) ($this->cotacoes[$codigo] ?? 0);
    }
}
