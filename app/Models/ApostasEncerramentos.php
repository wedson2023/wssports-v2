<?php

namespace App\Models;

use App\Enums\AceitarAlteracoes;
use App\Enums\ModalidadeEncerramento;
use App\Enums\SituacaoEncerramento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pedido de encerramento de uma aposta, aceito ou recusado (spec 010, data-model seção 1).
 */
class ApostasEncerramentos extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_encerramentos';

    /**
     * Gravado só pelos serviços de encerramento, nunca direto da requisição.
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
            'modalidade' => ModalidadeEncerramento::class,
            'situacao' => SituacaoEncerramento::class,
            'aceitar_alteracoes' => AceitarAlteracoes::class,
            'oferta_exibida' => 'decimal:2',
            'oferta' => 'decimal:2',
            'valor_pedido' => 'decimal:2',
            'valor' => 'decimal:2',
            'margem' => 'decimal:2',
            'proporcao' => 'decimal:10',
            'fracao_antes' => 'decimal:10',
            'fracao_depois' => 'decimal:10',
            'valor_apostado_antes' => 'decimal:2',
            'valor_apostado_depois' => 'decimal:2',
            'premio_antes' => 'decimal:2',
            'premio_depois' => 'decimal:2',
            'palpites' => 'array',
            'rollovers' => 'array',
            'decidido_em' => 'datetime',
            'creditar_em' => 'datetime',
            'creditado_em' => 'datetime',
            'desfeito_em' => 'datetime',
        ];
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Apostas::class, 'apostas_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id')->withTrashed();
    }

    /**
     * Usuário do painel que encerrou em acesso assistido.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function automatico(): BelongsTo
    {
        return $this->belongsTo(ApostasEncerramentosAutomaticos::class, 'apostas_encerramentos_automaticos_id');
    }

    public function transacao(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'clientes_transacoes_id');
    }

    public function transacao_estorno(): BelongsTo
    {
        return $this->belongsTo(ClientesTransacoes::class, 'estorno_clientes_transacoes_id');
    }

    /**
     * Encerramentos aceitos que continuam valendo (Registrado ou Creditado).
     */
    public function scopeQueValem(Builder $consulta): Builder
    {
        return $consulta->whereIn('situacao', SituacaoEncerramento::valores_que_valem());
    }
}
