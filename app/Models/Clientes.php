<?php

namespace App\Models;

use App\Enums\Genero;
use Database\Factories\ClientesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Cliente (apostador). Autentica pelo guard "clientes", separado dos usuários do painel.
 */
class Clientes extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<ClientesFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    /**
     * Os saldos e a data de corte dos tokens ficam de fora: só mudam por métodos próprios.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'ddi',
        'telefone',
        'email',
        'password',
        'cpf',
        'data_nascimento',
        'genero',
        'codigo_afiliado',
        'ativo',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'data_nascimento' => 'date',
            'genero' => Genero::class,
            'ativo' => 'boolean',
            'saldo' => 'decimal:2',
            'saldo_promocao_esportes' => 'decimal:2',
            'saldo_promocao_cassino' => 'decimal:2',
            'tokens_validos_desde' => 'datetime',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function configuracoes(): HasOne
    {
        return $this->hasOne(ClientesConfiguracoes::class, 'clientes_id');
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(ClientesTransacoes::class, 'clientes_id');
    }

    public function meios_pagamento(): HasMany
    {
        return $this->hasMany(ClientesMeiosPagamento::class, 'clientes_id');
    }

    public function codigos_recuperacao(): HasMany
    {
        return $this->hasMany(ClientesCodigosRecuperacao::class, 'clientes_id');
    }

    /**
     * Verificação única de acesso da área do cliente: só clientes ativos e não excluídos.
     */
    public function pode_acessar(): bool
    {
        return $this->ativo && ! $this->trashed();
    }

    /**
     * Faz todos os tokens já emitidos deixarem de valer (o middleware compara o iat do token).
     */
    public function invalidar_tokens(): void
    {
        $this->forceFill(['tokens_validos_desde' => now()])->save();
    }
}
