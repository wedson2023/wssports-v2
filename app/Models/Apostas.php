<?php

namespace App\Models;

use App\Enums\AceitarAlteracoes;
use App\Enums\FormaPagamento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoAposta;
use App\Support\CodigoAposta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aposta de um visitante (código Pendente), de um vendedor ou de um cliente.
 */
class Apostas extends Model
{
    use SoftDeletes;

    protected $table = 'apostas';

    /**
     * Todos os campos são definidos pelos serviços de aposta, nunca direto da requisição.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'situacao' => SituacaoAposta::class,
            'resultado' => ResultadoAposta::class,
            'tipo' => TipoAposta::class,
            'forma_pagamento' => FormaPagamento::class,
            'aceitar_alteracoes' => AceitarAlteracoes::class,
            'valor' => 'decimal:2',
            'cotacao_total' => 'decimal:2',
            'premio' => 'decimal:2',
            'valor_acrescido' => 'decimal:2',
            'comissao' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'comissao_por_premio' => 'decimal:2',
            'multiplicador' => 'integer',
            'premio_maximo' => 'decimal:2',
            'ganho_multiplo_palpites' => 'decimal:2',
            'tempo_cancelamento_aposta' => 'integer',
            'recebida_em' => 'datetime',
            'validada_em' => 'datetime',
            'decidida_em' => 'datetime',
            'confirmada_em' => 'datetime',
            'expira_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function palpites(): HasMany
    {
        return $this->hasMany(ApostasPalpites::class, 'apostas_id');
    }

    /**
     * Palpites que contam na cotação total (os cancelados por edição ficam de fora).
     */
    public function palpites_ativos(): HasMany
    {
        return $this->palpites()->where('situacao', SituacaoPalpite::Ativo);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(ApostasHistorico::class, 'apostas_id');
    }

    public function rollovers(): HasMany
    {
        return $this->hasMany(ApostasRollovers::class, 'apostas_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    public function autor_cancelamento(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'cancelada_por')->withTrashed();
    }

    /**
     * Consulta pelo código como o apostador digitou (maiúsculas, sem espaços).
     */
    public static function buscar_pelo_codigo(string $codigo): Builder
    {
        return static::query()->where('codigo', CodigoAposta::normalizar($codigo));
    }

    /**
     * Total que o apostador recebe se ganhar: prêmio + acréscimo por múltiplos palpites.
     */
    public function total_a_pagar(): string
    {
        return bcadd((string) $this->premio, (string) $this->valor_acrescido, 2);
    }

    /**
     * Delay do ao vivo do apostador desta aposta (configuração do cliente ou do vendedor).
     */
    public function delay_ao_vivo(): int
    {
        return (int) ($this->clientes_id !== null
            ? ClientesConfiguracoes::where('clientes_id', $this->clientes_id)->value('delay_ao_vivo')
            : UsuariosConfiguracoes::where('usuarios_id', $this->usuarios_id)->value('delay_ao_vivo'));
    }

    /**
     * Segundos até o fim do delay da aposta Em análise (0 quando já passou).
     */
    public function segundos_restantes(): int
    {
        $fim = $this->recebida_em->copy()->addSeconds($this->delay_ao_vivo());

        return (int) max(0, ceil(now()->diffInSeconds($fim, false)));
    }
}
