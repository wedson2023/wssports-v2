<?php

namespace App\Models;

use App\Enums\SituacaoEspecial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoria especial (ex.: "Campeão Brasileiro 2026"): o apostador escolhe uma opção, com cotação
 * fixa, até a data limite. Encerrada com uma ou mais opções vencedoras (quantidade_vencedoras, com
 * divisão da cotação das empatadas na última vaga) ou cancelada pelo administrador; o resultado só
 * muda pela correção do Admin (spec 024).
 */
class Especiais extends Model
{
    use SoftDeletes;

    protected $table = 'especiais';

    /**
     * Padrões das colunas, para a categoria recém-criada já responder com eles.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'situacao' => 'Aguardando',
        'ativo' => true,
        'quantidade_vencedoras' => 1,
        'dividir_empate' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'data_limite',
        'quantidade_vencedoras',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_limite' => 'datetime',
            'situacao' => SituacaoEspecial::class,
            'quantidade_vencedoras' => 'integer',
            'dividir_empate' => 'boolean',
            'ativo' => 'boolean',
            'encerrado_em' => 'datetime',
        ];
    }

    /**
     * Opções com a categoria já ligada (chaperone), para a situação de cada uma não consultar de novo.
     */
    public function opcoes(): HasMany
    {
        return $this->hasMany(EspeciaisOpcoes::class, 'especiais_id')->chaperone('especial');
    }

    public function vencedoras(): HasMany
    {
        return $this->opcoes()->where('vencedora', true);
    }

    /**
     * Correções de resultado (spec 024), a mais recente primeiro.
     */
    public function correcoes(): HasMany
    {
        return $this->hasMany(EspeciaisCorrecoes::class, 'especiais_id')->latest('id');
    }

    public function encerrado_por_usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'encerrado_por')->withTrashed();
    }

    public function palpites(): HasMany
    {
        return $this->hasMany(ApostasPalpites::class, 'especiais_id');
    }

    /**
     * Ainda aceita palpites: ativa, aguardando e antes da data limite.
     */
    public function aceita_palpites(): bool
    {
        return $this->ativo && $this->situacao === SituacaoEspecial::Aguardando && $this->data_limite->isFuture();
    }

    /**
     * Aguardando com a data limite já passada: falta encerrar (selo "Aguardando resultado").
     */
    public function aguardando_resultado(): bool
    {
        return $this->situacao === SituacaoEspecial::Aguardando && $this->data_limite->isPast();
    }

    /**
     * Visíveis ao público: ativas, aguardando, com data limite futura e ao menos uma opção ativa.
     */
    public function scopeVisiveis(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true)
            ->where('situacao', SituacaoEspecial::Aguardando->value)
            ->where('data_limite', '>', now())
            ->whereHas('opcoes', fn (Builder $opcoes) => $opcoes->where('ativo', true));
    }
}
