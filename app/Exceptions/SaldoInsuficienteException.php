<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Débito maior que o saldo da carteira: a operação é recusada sem alterar nada.
 */
class SaldoInsuficienteException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Saldo insuficiente.'], 422);
    }
}
