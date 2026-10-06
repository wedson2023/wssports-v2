<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Porcentagens de ajuste do pré-jogo para os clientes do site. Sem cliente = regra geral
 * (visitantes e todos os clientes); com cliente = regra dele, somada à geral.
 */
class PorcentagensClientes extends Model
{
    use SoftDeletes;

    protected $table = 'porcentagens_clientes';

    /**
     * chave_cliente é gerada pelo banco.
     *
     * @var list<string>
     */
    protected $fillable = ['clientes_id', 'valores'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valores' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }
}
