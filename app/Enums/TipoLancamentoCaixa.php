<?php

namespace App\Enums;

/**
 * Tipo do lançamento no caixa do vendedor (spec 017): o suprimento é dinheiro entregue a ele (aumenta
 * o que ele deve); a retirada é dinheiro recolhido (diminui o que ele deve).
 */
enum TipoLancamentoCaixa: string
{
    case Suprimento = 'Suprimento';
    case Retirada = 'Retirada';
}
