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
 * fixa, até a data limite. Encerrada com a opção vencedora ou cancelada pelo administrador.
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
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'data_limite',
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
            'ativo' => 'boolean',
            'encerrado_em' => 'datetime',
        ];
    }

    public function opcoes(): HasMany
    {
        return $this->hasMany(EspeciaisOpcoes::class, 'especiais_id');
    }

    public function opcao_vencedora(): BelongsTo
    {
        return $this->belongsTo(EspeciaisOpcoes::class, 'especiais_opcoes_id_vencedora')->withTrashed();
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
