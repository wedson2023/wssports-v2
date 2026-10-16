<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançamento do provedor do cassino recusado pela carteira (spec 011, R-14): desfaz a transação e
 * leva o código de erro devolvido ao provedor (ex.: fail_balance, unknown_action, wrong_params).
 */
class RecusaCarteiraCassinoException extends RuntimeException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}
