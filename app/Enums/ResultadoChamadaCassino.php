<?php

namespace App\Enums;

/**
 * Resultado de uma chamada recebida do provedor do cassino (spec 011, R-15).
 */
enum ResultadoChamadaCassino: string
{
    case Aceita = 'Aceita';
    // lançamento repetido (mesmo tradeId): respondido sem mover saldo
    case Repetida = 'Repetida';
    case Recusada = 'Recusada';
    case Erro = 'Erro';
}
