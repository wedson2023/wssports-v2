<?php

namespace App\Enums;

/**
 * Movimento do cliente que gerou o lançamento de comissão do afiliado.
 */
enum EventoLancamentoAfiliado: string
{
    case Depósito = 'Depósito';
    case Saque = 'Saque';
    case EstornoDepósito = 'Estorno de depósito';
}
