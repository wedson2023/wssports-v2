<?php

namespace App\Models;

use App\Enums\SituacaoEncerramentoAutomatico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Valor-alvo de encerramento automático programado pelo cliente (spec 010, data-model seção 2).
 */
class ApostasEncerramentosAutomaticos extends Model
{
    use SoftDeletes;

    protected $table = 'apostas_encerramentos_automaticos';

    /**
     * Gravado só pelo serviço EncerramentosAutomaticos.
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
            'situacao' => SituacaoEncerramentoAutomatico::class,
            'valor_alvo' => 'decimal:2',
            'executado_em' => 'datetime',
            'cancelado_em' => 'datetime',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id')->withTrashed();
    }

    public function scopeAtivos(Builder $consulta): Builder
    {
        return $consulta->where('situacao', SituacaoEncerramentoAutomatico::Ativo);
    }

    /**
     * Cancela o automático ativo da aposta (no máximo um), com o motivo.
     *
     * @return int quantos foram cancelados (0 ou 1)
     */
    public static function cancelar_ativo(int $apostas_id, string $motivo): int
    {
        return static::ativos()->where('apostas_id', $apostas_id)->update([
            'situacao' => SituacaoEncerramentoAutomatico::Cancelado->value,
            'cancelado_em' => now(),
            'motivo_cancelamento' => $motivo,
            'updated_at' => now(),
        ]);
    }
}
