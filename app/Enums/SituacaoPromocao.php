<?php

namespace App\Enums;

/**
 * Situação de uma promoção na tela Bônus (spec 020, FR-007, R-07); calculada, não gravada.
 */
enum SituacaoPromocao: string
{
    case Vigente = 'Vigente';
    case Agendada = 'Agendada';
    case Encerrada = 'Encerrada';
    case Desativada = 'Desativada';
    case Estornando = 'Estornando';
    case Estornada = 'Estornada';
}
