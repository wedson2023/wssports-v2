<?php

namespace App\Enums;

/**
 * Por que o sistema cancelou um palpite (spec 009, R-07). A apuração só restaura os que ela mesma
 * devolveu por empate; os de jogo cancelado ficam cancelados.
 */
enum MotivoCancelamentoPalpite: string
{
    case JogoCancelado = 'Jogo cancelado';
    case EmpateAnulaAposta = 'Empate anula aposta';
}
