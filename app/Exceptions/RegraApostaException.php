<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Aposta recusada por uma regra (permissão, jogo, limite, saldo...). Nada é gravado. Com
 * palpites indisponíveis, a resposta os lista para o apostador removê-los.
 */
class RegraApostaException extends Exception
{
    /**
     * @param  list<array<string, mixed>>  $indisponiveis
     */
    public function __construct(string $mensagem, public readonly array $indisponiveis = [])
    {
        parent::__construct($mensagem);
    }

    public function render(): JsonResponse
    {
        $corpo = ['message' => $this->getMessage()];

        if ($this->indisponiveis !== []) {
            $corpo['indisponiveis'] = $this->indisponiveis;
        }

        return response()->json($corpo, 422);
    }
}
