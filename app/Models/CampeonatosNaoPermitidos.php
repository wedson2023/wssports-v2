<?php

namespace App\Models;

use App\Enums\AlvoRegra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Campeonato que não é exibido para um alvo. Usado só nas listagens, nunca nas
 * cargas. Desmarcar é exclusão lógica; marcar de novo restaura o registro.
 */
class CampeonatosNaoPermitidos extends Model
{
    use SoftDeletes;

    protected $table = 'campeonatos_nao_permitidos';

    /**
     * chave_usuario e chave_cliente são geradas pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['campeonatos_id', 'alvo', 'usuarios_id', 'clientes_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alvo' => AlvoRegra::class,
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }
}
