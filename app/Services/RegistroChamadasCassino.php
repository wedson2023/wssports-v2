<?php

namespace App\Services;

use App\Enums\ResultadoChamadaCassino;
use App\Models\CassinosChamadas;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra toda chamada recebida do provedor do cassino, sem a chave e a assinatura (spec 011,
 * FR-026, R-15). Fica fora da transação do saldo: falhar ao registrar nunca desfaz a rodada.
 */
class RegistroChamadasCassino
{
    /**
     * @param  array<string, mixed>  $dados
     * @param  array<string, mixed>  $resposta
     */
    public function registrar(array $dados, array $resposta, ResultadoChamadaCassino $resultado, ?string $ip, int $duracao_ms): void
    {
        unset($dados['key'], $dados['sign']);

        $texto = fn (string $campo, int $tamanho) => isset($dados[$campo]) && is_scalar($dados[$campo]) ? mb_substr((string) $dados[$campo], 0, $tamanho) : null;

        try {
            CassinosChamadas::create([
                'comando' => $texto('cmd', 30),
                'login' => $texto('login', 60),
                'trade_id' => $texto('tradeId', 100),
                'sessao_provedor' => $texto('sessionId', 100),
                'dados' => $dados,
                'resposta' => $resposta,
                'resultado' => $resultado,
                'erro' => ($resposta['status'] ?? null) === 'fail' ? mb_substr((string) ($resposta['error'] ?? ''), 0, 60) : null,
                'ip' => $ip,
                'duracao_ms' => $duracao_ms,
            ]);
        } catch (Throwable $erro) {
            Log::warning('Cassino: não foi possível registrar a chamada do provedor ('.class_basename($erro).').');
        }
    }
}
