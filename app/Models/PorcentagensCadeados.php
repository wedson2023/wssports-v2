<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cadeado de uma cotação posto por Admin ou Supervisor na tela de um usuário da rede: ele e quem
 * está abaixo dele não alteram a porcentagem daquele código (spec 016). Destrancar = soft delete.
 */
class PorcentagensCadeados extends Model
{
    use SoftDeletes;

    public const VENDEDORES = 'vendedores';

    public const CAMPEONATOS = 'campeonatos';

    protected $table = 'porcentagens_cadeados';

    /**
     * chave_tipo e chave_campeonato são geradas pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['usuarios_id', 'trancado_por', 'camada', 'tipo', 'campeonatos_id', 'codigo'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'trancado_por')->withTrashed();
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonatos::class, 'campeonatos_id');
    }
}
