<?php

namespace App\Enums;

/**
 * Meio de pagamento usado no depósito do cliente.
 */
enum MeioDeposito: string
{
    case Pix = 'Pix';
    case CartãoCrédito = 'Cartão de crédito';
}
