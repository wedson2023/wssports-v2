<?php

namespace App\Models;

use App\Enums\TipoChavePix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chave Pix do afiliado (uma por afiliado), usada para pagar a prestação de contas.
 */
class AfiliadosMeiosPagamento extends Model
{
    use SoftDeletes;

    protected $table = 'afiliados_meios_pagamento';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afiliados_id',
        'pix_tipo_chave',
        'pix_chave',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pix_tipo_chave' => TipoChavePix::class,
        ];
    }

    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliados::class, 'afiliados_id');
    }
}
