<?php

namespace App\Models;

use App\Services\ArmazenamentoImagens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Provedor real dos jogos do cassino (spec 011, data-model seção 1).
 */
class CassinosProvedores extends Model
{
    use SoftDeletes;

    protected $table = 'cassinos_provedores';

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
            'ativo' => 'boolean',
            'ordem' => 'integer',
            'porcentagem_comissao' => 'decimal:2',
            'titulos_provedor' => 'array',
        ];
    }

    public function jogos(): HasMany
    {
        return $this->hasMany(CassinosJogos::class, 'cassinos_provedores_id');
    }

    public function scopeAtivos(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true);
    }

    /**
     * Endereço da logo: as logos do sistema antigo ficam em public/images (caminho com "/"); as
     * enviadas pelo painel, no disco público.
     */
    public function url_imagem(): ?string
    {
        if ($this->imagem === null) {
            return null;
        }

        return str_starts_with($this->imagem, '/') ? $this->imagem : ArmazenamentoImagens::url($this->imagem);
    }

    /**
     * Código do provedor a partir do "title" recebido: minúsculas, sem espaço, "_" nem "-", unificado
     * pelos apelidos de config/cassino.php (R-02).
     */
    public static function normalizar_codigo(string $titulo): string
    {
        $codigo = preg_replace('/[\s_\-]+/', '', mb_strtolower(trim($titulo)));

        return config('cassino.apelidos_provedores')[$codigo] ?? $codigo;
    }
}
