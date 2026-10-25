<?php

namespace App\Enums;

/**
 * Como o bônus de um cliente foi estornado (spec 020): pelo painel só nele, pelo estorno da promoção
 * inteira ou pelo sistema (estorno do depósito ou da indicação que deu o bônus).
 */
enum TipoEstornoBonus: string
{
    case Individual = 'Individual';
    case Geral = 'Geral';
    case Automático = 'Automático';
}
