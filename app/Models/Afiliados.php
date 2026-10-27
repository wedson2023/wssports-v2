<?php

namespace App\Models;

use App\Enums\OrigemCadastroAfiliado;
use App\Enums\RedeSocialAfiliado;
use App\Enums\SituacaoAfiliado;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Afiliado: indica clientes pelo link (?user=código) e recebe comissão sobre os movimentos deles.
 * Autentica pelo guard "afiliados", separado do painel e dos clientes (spec 012).
 */
class Afiliados extends Authenticatable implements JWTSubject
{
    use SoftDeletes;

    protected $table = 'afiliados';

    /**
     * Situação, decisão e data de corte dos tokens ficam de fora: só mudam por métodos próprios.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'login',
        'password',
        'email',
        'cpf',
        'ddi',
        'telefone',
        'cidade',
        'estado',
        'endereco',
        // perfil na rede social (spec 022)
        'rede_social',
        'link_perfil',
        'quantidade_seguidores',
        'sobre',
        'codigo',
        'origem_cadastro',
        'usuarios_id',
        'cadastrado_por',
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
            'situacao' => SituacaoAfiliado::class,
            'origem_cadastro' => OrigemCadastroAfiliado::class,
            'rede_social' => RedeSocialAfiliado::class,
            'quantidade_seguidores' => 'integer',
            'decidido_em' => 'datetime',
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

    /**
     * Gerente responsável (usuário do painel com a função Gerente).
     */
    public function gerente(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    /**
     * Usuário do painel que cadastrou o afiliado (nulo no cadastro público).
     */
    public function autor_cadastro(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'cadastrado_por')->withTrashed();
    }

    /**
     * Usuário do painel que aprovou ou recusou o cadastro público.
     */
    public function autor_decisao(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'decidido_por')->withTrashed();
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(AfiliadosComissoes::class, 'afiliados_id');
    }

    public function comissao_vigente(): HasOne
    {
        // só uma regra fica sem fim de vigência (RegraComissaoAfiliados)
        return $this->hasOne(AfiliadosComissoes::class, 'afiliados_id')->whereNull('vigente_ate');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(AfiliadosLancamentos::class, 'afiliados_id');
    }

    public function prestacoes(): HasMany
    {
        return $this->hasMany(AfiliadosPrestacoesContas::class, 'afiliados_id');
    }

    public function ultima_prestacao(): HasOne
    {
        return $this->hasOne(AfiliadosPrestacoesContas::class, 'afiliados_id')->latestOfMany('periodo_fim');
    }

    public function meio_pagamento(): HasOne
    {
        return $this->hasOne(AfiliadosMeiosPagamento::class, 'afiliados_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Clientes::class, 'afiliados_id');
    }

    public function codigos_recuperacao(): HasMany
    {
        return $this->hasMany(AfiliadosCodigosRecuperacao::class, 'afiliados_id');
    }

    /**
     * Verificação única de acesso da área do afiliado: só ativos e fora da lixeira.
     */
    public function pode_acessar(): bool
    {
        return $this->situacao === SituacaoAfiliado::Ativo && ! $this->trashed();
    }

    /**
     * Só o afiliado que pode entrar liga clientes novos pelo código (FR-023).
     */
    public function pode_receber_clientes(): bool
    {
        return $this->pode_acessar();
    }

    /**
     * Faz todos os tokens já emitidos deixarem de valer (o middleware compara o iat do token).
     */
    public function invalidar_tokens(): void
    {
        $this->forceFill(['tokens_validos_desde' => now()])->save();
    }

    /**
     * Código público de 8 caracteres, sem letras e números que se confundem (0, O, 1, I), único
     * também entre os excluídos.
     */
    public static function gerar_codigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $codigo = '';

            for ($posicao = 0; $posicao < 8; $posicao++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (static::withTrashed()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
