<?php

namespace App\Enums;

enum TipoTransacao: string
{
    case Crédito = 'Crédito';
    case Débito = 'Débito';
}
