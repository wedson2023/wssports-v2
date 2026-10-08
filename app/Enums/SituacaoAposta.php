<?php

namespace App\Enums;

/**
 * Situação de uma aposta (transições na research R-12 da spec 004).
 */
enum SituacaoAposta: string
{
    case Pendente = 'Pendente';
    case EmAnálise = 'Em análise';
    case Ativa = 'Ativa';
    case Recusada = 'Recusada';
    case Expirada = 'Expirada';
    case Cancelada = 'Cancelada';
}
