<?php

namespace App\Models;

use App\Enums\TipoChavePix;
use App\Enums\TipoConta;
use App\Enums\TipoMeioPagamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Meio de pagamento do cliente: Pix ou transferência bancária.
 * O cliente e o principal são definidos pelo serviço MeiosPagamentoClientes.
 */
class ClientesMeiosPagamento extends Model
{
    use SoftDeletes;

    /**
     * Campos de cada tipo; os do outro tipo ficam nulos.
     */
    public const CAMPOS_PIX = ['pix_nome_titular', 'pix_tipo_chave', 'pix_chave'];

    public const CAMPOS_TRANSFERENCIA = [
        'banco_codigo',
        'banco_nome',
        'agencia',
        'conta',
        'conta_digito',
        'conta_tipo',
        'titular_nome',
        'titular_documento',
    ];

    protected $table = 'clientes_meios_pagamento';

    /**
     * @var list<string>
     */
    protected $fillable = ['tipo', ...self::CAMPOS_PIX, ...self::CAMPOS_TRANSFERENCIA];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMeioPagamento::class,
            'pix_tipo_chave' => TipoChavePix::class,
            'conta_tipo' => TipoConta::class,
            'principal' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }
}
