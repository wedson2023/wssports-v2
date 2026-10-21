<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uma alteração de regra de cotação (porcentagem, valor do jogo ou teto) ou um trancar/destrancar,
 * com os códigos alterados e o valor antes e depois (spec 016, FR-018a).
 */
class PorcentagensHistoricos extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuarios_id', 'camada', 'acao', 'tipo', 'alvo', 'alvo_usuarios_id', 'clientes_id', 'campeonatos_id', 'confrontos_id', 'alteracoes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alteracoes' => 'array',
        ];
    }

    // o nome do autor continua aparecendo mesmo se ele for excluído
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }
}
