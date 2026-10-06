<?php

namespace App\Models;

use App\Enums\AlvoRegra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jogo da grade que não é exibido no ao vivo para um alvo. Usado só nas listagens, nunca nas
 * cargas. Desmarcar é exclusão lógica; marcar de novo restaura o registro.
 */
class ConfrontosAoVivoNaoPermitidos extends Model
{
    use SoftDeletes;

    protected $table = 'confrontos_ao_vivo_nao_permitidos';

    /**
     * chave_usuario e chave_cliente são geradas pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['confrontos_id', 'alvo', 'usuarios_id', 'clientes_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alvo' => AlvoRegra::class,
        ];
    }

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id');
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
