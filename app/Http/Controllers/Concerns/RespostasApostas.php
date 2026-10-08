<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SituacaoAposta;
use App\Http\Resources\ComprovanteApostaResource;
use App\Models\Apostas;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

trait RespostasApostas
{
    /**
     * Janela, em segundos, em que as tentativas são contadas.
     */
    private const JANELA_TENTATIVAS = 60;

    /**
     * Conta a tentativa e interrompe com 429 quando o limite foi atingido (R-10).
     */
    private function limitar_tentativas(string $chave, int $maximo): void
    {
        if (RateLimiter::tooManyAttempts($chave, $maximo)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_TENTATIVAS);
    }

    /**
     * Resposta da criação: 200 no envio repetido, 202 Em análise e 201 nos demais.
     */
    private function resposta_criacao(Apostas $aposta, bool $repetida, bool $mostrar_comissao): JsonResponse
    {
        $status = match (true) {
            $repetida => 200,
            $aposta->situacao === SituacaoAposta::EmAnálise => 202,
            default => 201,
        };

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), $mostrar_comissao)], $status);
    }
}
