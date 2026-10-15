<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A oferta de encerramento que o cliente aceitou não vale mais (token vencido ou adulterado, cotação
 * ou placar mudou): nada é encerrado e a página mostra a oferta atual (spec 010, R-09).
 */
class OfertaAlteradaException extends Exception
{
    public function __construct(string $mensagem = 'A oferta mudou.')
    {
        parent::__construct($mensagem);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
