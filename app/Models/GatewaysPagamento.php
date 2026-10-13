<?php

namespace App\Models;

use App\Enums\SituacaoGateway;
use App\Exceptions\GatewayIndisponivelException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conta do Mercado Pago vinculada pela banca (spec 007, US8). Os tokens ficam criptografados e
 * nunca saem em resource nem log.
 */
class GatewaysPagamento extends Model
{
    use SoftDeletes;

    protected $table = 'gateways_pagamento';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected $hidden = ['access_token', 'refresh_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'situacao' => SituacaoGateway::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expira_em' => 'datetime',
            'vinculado_em' => 'datetime',
            'renovado_em' => 'datetime',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'vinculado_por');
    }

    /**
     * Vínculo em uso (vinculado ou com falha na renovação, ainda dentro da validade).
     */
    public static function atual(): ?self
    {
        return static::where('situacao', '!=', SituacaoGateway::Desvinculado)->latest('id')->first();
    }

    /**
     * Access token da conta vinculada para chamar a API do Mercado Pago.
     *
     * @throws GatewayIndisponivelException sem vínculo ou com a autorização vencida
     */
    public static function token_atual(): string
    {
        $gateway = static::atual();

        if ($gateway === null || $gateway->access_token === null || $gateway->expira_em->isPast()) {
            throw new GatewayIndisponivelException;
        }

        return $gateway->access_token;
    }
}
