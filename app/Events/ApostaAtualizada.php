<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A aposta mudou (apuração, encerramento ou automático): o bilhete aberto busca os dados de novo
 * (spec 010, contracts/tempo_real.md). O evento não traz valores, só o motivo.
 */
class ApostaAtualizada implements ShouldBroadcastNow
{
    public function __construct(public string $codigo, public string $motivo) {}

    /**
     * Envia sem deixar a falha do servidor de tempo real interromper quem chamou.
     */
    public static function emitir(string $codigo, string $motivo): void
    {
        try {
            // event() envia na hora (ShouldBroadcastNow), dentro do try
            event(new self($codigo, $motivo));
        } catch (Throwable $erro) {
            Log::warning("Tempo real: aposta {$codigo} não enviada: ".class_basename($erro).' - '.$erro->getMessage());
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('apostas.'.$this->codigo);
    }

    public function broadcastAs(): string
    {
        return 'aposta.atualizada';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['codigo' => $this->codigo, 'motivo' => $this->motivo, 'enviado_em' => now()->toIso8601String()];
    }
}
