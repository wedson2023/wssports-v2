<?php

namespace App\Models;

use App\Enums\CanalMensagem;
use App\Enums\SituacaoEnvioMensagem;
use App\Enums\TipoMensagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro de um envio de mensagem por canal (spec 025, data-model), sem o conteúdo.
 */
class MensagensEnvios extends Model
{
    use SoftDeletes;

    protected $table = 'mensagens_envios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'canal',
        'destinatario',
        'situacao',
        'reserva',
        'tentativas',
        'erro',
        'enviado_em',
        'referencia_tipo',
        'referencia_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMensagem::class,
            'canal' => CanalMensagem::class,
            'situacao' => SituacaoEnvioMensagem::class,
            'reserva' => 'boolean',
            'tentativas' => 'integer',
            'enviado_em' => 'datetime',
        ];
    }
}
