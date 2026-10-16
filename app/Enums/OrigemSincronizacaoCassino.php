<?php

namespace App\Enums;

/**
 * Quem disparou a carga do catálogo do cassino (spec 011, R-04).
 */
enum OrigemSincronizacaoCassino: string
{
    case Agendada = 'Agendada';
    case Painel = 'Painel';
}
