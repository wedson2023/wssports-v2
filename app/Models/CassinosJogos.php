<?php

namespace App\Models;

use App\Enums\AparelhoCassino;
use App\Enums\DestaqueProvedorCassino;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Jogo do cassino, único por provedor e nome (spec 011, data-model seção 2, R-03).
 */
class CassinosJogos extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_jogos';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aparelhos' => AparelhoCassino::class,
            'destaque_provedor' => DestaqueProvedorCassino::class,
            'popular' => 'boolean',
            'ativo' => 'boolean',
            'ordem' => 'integer',
            'aberturas' => 'integer',
            'removido_em' => 'datetime',
        ];
    }

    public function provedor(): BelongsTo
    {
        return $this->belongsTo(CassinosProvedores::class, 'cassinos_provedores_id');
    }

    public function canais(): HasMany
    {
        return $this->hasMany(CassinosJogosCanais::class, 'cassinos_jogos_id');
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(CassinosSessoes::class, 'cassinos_jogos_id');
    }

    /**
     * Jogos que aparecem no site para o aparelho de quem vê (FR-010): ativos, ainda na lista do
     * provedor, de provedor ativo, que funcionam no aparelho e com pelo menos um canal abrível.
     */
    public function scopeVisiveis(Builder $consulta, AparelhoCassino $aparelho): Builder
    {
        return $consulta
            ->where('cassinos_jogos.ativo', true)
            ->whereNull('cassinos_jogos.removido_em')
            ->whereIn('cassinos_jogos.aparelhos', [AparelhoCassino::Todos->value, $aparelho->value])
            ->whereHas('provedor', fn (Builder $provedor) => $provedor->where('ativo', true))
            ->whereHas('canais', fn (Builder $canal) => $canal->abriveis());
    }

    /**
     * Jogos que aparecem no site em pelo menos um aparelho (celular ou computador).
     */
    public function scopeVisiveisEmAlgumAparelho(Builder $consulta): Builder
    {
        return $consulta->where(fn (Builder $c) => $c->visiveis(AparelhoCassino::Celular)
            ->orWhere(fn (Builder $outro) => $outro->visiveis(AparelhoCassino::Computador)));
    }

    /**
     * Nome que identifica o jogo dentro do provedor: minúsculas, sem acentos e sem espaços
     * duplicados (R-03).
     */
    public static function normalizar_nome(string $nome): string
    {
        return (string) Str::of(Str::ascii($nome))->lower()->squish();
    }
}
