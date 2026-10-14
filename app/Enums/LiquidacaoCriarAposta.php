<?php

namespace App\Enums;

/**
 * Regra aplicada quando um palpite de um bloco Criar Aposta é cancelado ou anulado (spec 008).
 */
enum LiquidacaoCriarAposta: string
{
    // recalcula o bloco só com os palpites restantes, mantendo o desconto
    case Recalcular = 'Recalcular';
    // cancela o bloco inteiro, que passa a valer 1,00
    case AnularBloco = 'Anular bloco';
}
