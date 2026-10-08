<?php

namespace App\Enums;

/**
 * Origem do valor que o cliente precisa apostar antes de sacar ou converter.
 */
enum TipoRollover: string
{
    case Depósito = 'Depósito';
    case Bônus = 'Bônus';
}
