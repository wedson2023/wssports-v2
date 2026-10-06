<?php

namespace App\Models;

use App\Enums\AlvoRegra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Valor fixo somado à cotação de um confronto do pré-jogo, por alvo.
 */
class PorcentagensConfrontos extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_confrontos';

    /**
     * chave_usuario é gerada pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['confrontos_id', 'alvo', 'usuarios_id', 'valores'];

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

    public function confronto(): BelongsTo
    {
        return $this->belongsTo(Confrontos::class, 'confrontos_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
