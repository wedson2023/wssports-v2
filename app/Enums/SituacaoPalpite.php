<?php

namespace App\Enums;

/**
 * Situação de um palpite: cancelado por edição fica fora da cotação total.
 */
enum SituacaoPalpite: string
{
    case Ativo = 'Ativo';
    case Cancelado = 'Cancelado';
}
