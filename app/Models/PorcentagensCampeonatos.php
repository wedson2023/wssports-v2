<?php

namespace App\Models;

use App\Enums\AlvoRegra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Porcentagens de ajuste de um campeonato, por alvo (Clientes, Vendedores de um dono ou Todos).
 */
class PorcentagensCampeonatos extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_campeonatos';

    /**
     * chave_usuario é gerada pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['campeonatos_id', 'alvo', 'usuarios_id', 'valores'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alvo' => AlvoRegra::class,
            'valores' => 'array',
        ];
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonatos::class, 'campeonatos_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
