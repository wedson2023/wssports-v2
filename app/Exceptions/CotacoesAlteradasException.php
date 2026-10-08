<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A cotação de algum palpite mudou fora da preferência do apostador: nada é gravado e a resposta
 * traz as cotações novas e o novo prêmio, para ele confirmar reenviando.
 */
class CotacoesAlteradasException extends Exception
{
    /**
     * @param  list<array<string, mixed>>  $alteracoes
     * @param  array{cotacao_total: string, premio: string, valor_acrescido: string, total_a_pagar: string}  $calculo
     */
    public function __construct(public readonly array $alteracoes, public readonly array $calculo)
    {
        parent::__construct('Houve alteração nas cotações. Confira o novo prêmio e confirme para continuar.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'alteracoes' => $this->alteracoes,
            'cotacao_total' => $this->calculo['cotacao_total'],
            'premio' => $this->calculo['premio'],
            'valor_acrescido' => $this->calculo['valor_acrescido'],
            'total_a_pagar' => $this->calculo['total_a_pagar'],
        ], 409);
    }
}
