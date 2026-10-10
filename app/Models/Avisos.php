<?php

namespace App\Models;

use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aviso da banca (antigo "popup"): imagem com link e título opcionais, mostrada ao abrir o site
 * até o apostador marcar como lido.
 */
class Avisos extends Model
{
    use SoftDeletes;

    protected $table = 'avisos';

    /**
     * Padrão da coluna, para o aviso recém-criado já responder com ele.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativo' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titulo',
        'imagem',
        'link',
        'inicio_em',
        'fim_em',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio_em' => 'datetime',
            'fim_em' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    public function leituras(): HasMany
    {
        return $this->hasMany(AvisosLeituras::class, 'avisos_id');
    }

    public function url_imagem(): string
    {
        return ArmazenamentoImagens::url($this->imagem);
    }

    /**
     * Ativos e dentro do período de exibição.
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true)
            ->where(fn (Builder $inicio) => $inicio->whereNull('inicio_em')->orWhere('inicio_em', '<=', now()))
            ->where(fn (Builder $fim) => $fim->whereNull('fim_em')->orWhere('fim_em', '>=', now()));
    }
}
