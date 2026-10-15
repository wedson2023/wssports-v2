<?php

namespace App\Enums;

/**
 * Como a aposta foi encerrada antes do fim (spec 010): inteira, em parte ou pelo valor-alvo.
 */
enum ModalidadeEncerramento: string
{
    case Total = 'Total';
    case Parcial = 'Parcial';
    case Automático = 'Automático';
}
