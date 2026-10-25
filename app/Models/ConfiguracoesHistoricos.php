<?php

namespace App\Models;

use App\Enums\AcaoConfiguracao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uma alteração feita na tela Configurações (spec 021, FR-003a): quem, a aba ou seção, a ação e os
 * campos alterados com o valor antes e depois (segredos mascarados).
 */
class ConfiguracoesHistoricos extends Model
{
    use SoftDeletes;

    protected $table = 'configuracoes_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuarios_id', 'secao', 'acao', 'item_id', 'alteracoes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acao' => AcaoConfiguracao::class,
            'alteracoes' => 'array',
        ];
    }

    // o nome do autor continua aparecendo mesmo se ele for excluído
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
