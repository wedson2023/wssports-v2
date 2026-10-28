<?php

namespace App\Models;

use App\Enums\SituacaoEspecial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opção de uma categoria especial, com a cotação fixa cadastrada pelo administrador. Ao encerrar,
 * fica vencedora ou não (e empatada na última vaga); pode ser anulada antes disso (spec 024).
 */
class EspeciaisOpcoes extends Model
{
    use SoftDeletes;

    protected $table = 'especiais_opcoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'cotacao',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cotacao' => 'decimal:2',
            'ativo' => 'boolean',
            'vencedora' => 'boolean',
            'empatada' => 'boolean',
            'anulada_em' => 'datetime',
        ];
    }

    public function especial(): BelongsTo
    {
        return $this->belongsTo(Especiais::class, 'especiais_id')->withTrashed();
    }

    public function anulada_por_usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'anulada_por')->withTrashed();
    }

    public function anulada(): bool
    {
        return $this->anulada_em !== null;
    }

    /**
     * Situação mostrada no painel (data-model §2): Anulada, Vencedora ou Perdedora (categoria
     * encerrada), Ativa ou Desativada.
     */
    public function situacao_exibida(): string
    {
        return match (true) {
            $this->anulada() => 'Anulada',
            $this->especial->situacao === SituacaoEspecial::Encerrado => $this->vencedora ? 'Vencedora' : 'Perdedora',
            $this->ativo => 'Ativa',
            default => 'Desativada',
        };
    }
}
