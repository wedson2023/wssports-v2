<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Porcentagens de ajuste do pré-jogo de um usuário do painel (supervisor, gerente ou vendedor).
 */
class PorcentagensVendedores extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_vendedores';

    /**
     * @var list<string>
     */
    protected $fillable = ['usuarios_id', 'valores'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valores' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }
}
