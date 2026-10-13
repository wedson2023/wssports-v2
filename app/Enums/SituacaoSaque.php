<?php

namespace App\Enums;

/**
 * Situação do saque: Pendente (na fila), Processando (enviado ao Mercado Pago), Pago ou Recusado
 * (com devolução do valor ao saldo).
 */
enum SituacaoSaque: string
{
    case Pendente = 'Pendente';
    case Processando = 'Processando';
    case Pago = 'Pago';
    case Recusado = 'Recusado';
}
