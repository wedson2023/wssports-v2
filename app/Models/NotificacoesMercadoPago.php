<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aviso (webhook) recebido do Mercado Pago (spec 007, data-model §4).
 */
class NotificacoesMercadoPago extends Model
{
    use SoftDeletes;

    public const RECEBIDA = 'Recebida';

    public const PROCESSADA = 'Processada';

    public const IGNORADA = 'Ignorada';

    public const FALHOU = 'Falhou';

    protected $table = 'notificacoes_mercado_pago';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assinatura_valida' => 'boolean',
            'processada_em' => 'datetime',
        ];
    }

    public function marcar(string $situacao, ?string $motivo = null): void
    {
        $this->forceFill(['situacao' => $situacao, 'motivo' => $motivo, 'processada_em' => now()])->save();
    }
}
