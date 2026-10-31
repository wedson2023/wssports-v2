<?php

namespace App\Jobs;

use App\Exceptions\DestinatarioSemWhatsappException;
use App\Services\DisparoMensagens;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia uma mensagem por um canal (spec 025, research R-03 e R-04). A fila fica no banco, então o job
 * é criptografado: a mensagem pode levar um código de recuperação. A falha definitiva não repete; ao
 * esgotar as tentativas, aciona a reserva quando houver.
 */
class EnviarMensagem implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Códigos saem antes das demais: a ordem das filas do worker é apostas, notificacoes e default.
     */
    public const FILA_CODIGOS = 'notificacoes';

    public const FILA_DEMAIS = 'default';

    /**
     * Primeira tentativa + 3; prevalece sobre o --tries=1 do worker.
     */
    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string, mixed>  $mensagem
     */
    public function __construct(public int $envio_id, public array $mensagem, public bool $usar_reserva = false) {}

    public function handle(DisparoMensagens $disparo): void
    {
        try {
            $disparo->executar($this->envio_id, $this->mensagem);
        } catch (DestinatarioSemWhatsappException $erro) {
            $this->fail($erro);
        }
    }

    public function failed(?Throwable $erro): void
    {
        app(DisparoMensagens::class)->registrar_falha($this->envio_id, $this->mensagem, $erro, $this->usar_reserva);
    }
}
