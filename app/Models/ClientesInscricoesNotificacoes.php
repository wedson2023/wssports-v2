<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aparelho do cliente inscrito nas notificações push (spec 010, data-model seção 3).
 */
class ClientesInscricoesNotificacoes extends Model
{
    use SoftDeletes;

    protected $table = 'clientes_inscricoes_notificacoes';

    /**
     * Gravado só pelo serviço InscricoesNotificacoes.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * As chaves do aparelho nunca saem pela API.
     *
     * @var list<string>
     */
    protected $hidden = ['chave_p256dh', 'chave_auth', 'endpoint', 'endpoint_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receber_gols' => 'boolean',
            'receber_intervalo' => 'boolean',
            'receber_fim_jogo' => 'boolean',
            'receber_resultado_aposta' => 'boolean',
            'receber_encerramento_automatico' => 'boolean',
            'ultimo_envio_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clientes::class, 'clientes_id');
    }

    /**
     * Chave de busca do endpoint (o endpoint é longo demais para índice).
     */
    public static function hash_endpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
