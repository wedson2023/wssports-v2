<?php

namespace App\Exceptions;

use Exception;

/**
 * Conta do Mercado Pago não vinculada, desvinculada ou com a autorização vencida.
 */
class GatewayIndisponivelException extends Exception
{
    public function __construct(string $message = 'Depósitos indisponíveis no momento. Tente mais tarde.')
    {
        parent::__construct($message);
    }
}
