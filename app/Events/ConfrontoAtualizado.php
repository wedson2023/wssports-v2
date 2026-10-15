<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mudança de um jogo para o bilhete em tempo real (spec 010, contracts/tempo_real.md): canal
 * público confrontos.{id}, sem dado de cliente. Enviado na hora, sem fila.
 */
class ConfrontoAtualizado implements ShouldBroadcastNow
{
    private const FUTEBOL = 'FUTEBOL';

    /**
     * @param  array<string, mixed>  $dados  payload do contrato (montado por de_jogo)
     */
    public function __construct(public array $dados) {}

    /**
     * Envia sem deixar a falha do servidor de tempo real interromper quem chamou.
     */
    public static function emitir(array $dados): void
    {
        try {
            // event() envia na hora (ShouldBroadcastNow), dentro do try
            event(new self($dados));
        } catch (Throwable $erro) {
            Log::warning("Tempo real: jogo {$dados['confrontos_id']} não enviado: ".class_basename($erro).' - '.$erro->getMessage());
        }
    }

    /**
     * Payload a partir de uma linha do ao vivo ou do resultado (placar, gols por tempo e escanteios
     * com os nomes das colunas). Gols por tempo e escanteios só no futebol.
     *
     * @param  array<string, mixed>|object|null  $jogo  dados do jogo; nulo antes do início
     * @return array<string, mixed>
     */
    public static function de_jogo(int $confrontos_id, array|object|null $jogo, string $esporte, ?string $periodo, ?int $minuto, bool $gol, bool $cotacoes_alteradas, bool $encerrado = false): array
    {
        $jogo = $jogo !== null ? (array) $jogo : [];
        $futebol = $esporte === self::FUTEBOL;
        $tempo = fn (string $periodo_jogo) => $futebol && isset($jogo["gols_{$periodo_jogo}_casa"], $jogo["gols_{$periodo_jogo}_fora"])
            ? ['casa' => (int) $jogo["gols_{$periodo_jogo}_casa"], 'fora' => (int) $jogo["gols_{$periodo_jogo}_fora"]]
            : null;

        return [
            'confrontos_id' => $confrontos_id,
            'periodo' => $periodo,
            'minuto' => $minuto,
            'placar_casa' => isset($jogo['placar_casa']) ? (int) $jogo['placar_casa'] : null,
            'placar_fora' => isset($jogo['placar_fora']) ? (int) $jogo['placar_fora'] : null,
            'primeiro_tempo' => $tempo('primeiro_tempo'),
            'segundo_tempo' => $tempo('segundo_tempo'),
            'escanteios' => $futebol && isset($jogo['escanteios_casa'], $jogo['escanteios_fora'])
                ? (int) $jogo['escanteios_casa'] + (int) $jogo['escanteios_fora']
                : null,
            'gol' => $gol,
            'cotacoes_alteradas' => $cotacoes_alteradas,
            'encerrado' => $encerrado,
            'enviado_em' => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): Channel
    {
        return new Channel('confrontos.'.$this->dados['confrontos_id']);
    }

    public function broadcastAs(): string
    {
        return 'confronto.atualizado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->dados;
    }
}
