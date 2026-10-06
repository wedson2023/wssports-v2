<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Porcentagens de ajuste do ao vivo de um usuário do painel.
 */
class PorcentagensVendedoresAoVivo extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_vendedores_ao_vivo';

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
