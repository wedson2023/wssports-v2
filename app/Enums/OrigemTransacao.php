<?php

namespace App\Enums;

/**
 * O que gerou a movimentação de saldo.
 */
enum OrigemTransacao: string
{
    case AjusteManual = 'Ajuste manual';
    case Promoção = 'Promoção';
    case Aposta = 'Aposta';
    case Prêmio = 'Prêmio';
    case Estorno = 'Estorno';
}
