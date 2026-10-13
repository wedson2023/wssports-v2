<?php

namespace App\Enums;

/**
 * Como os saques são pagos: Manual (baixa no painel) ou Automático (pelo Mercado Pago, até o
 * limite configurado).
 */
enum ModoSaque: string
{
    case Manual = 'Manual';
    case Automático = 'Automático';
}
