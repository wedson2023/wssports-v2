<?php

namespace App\Enums;

/**
 * Saldo do cliente afetado por uma transação.
 */
enum Carteira: string
{
    case Saldo = 'Saldo';
    case PromoçãoEsportes = 'Promoção esportes';
    case PromoçãoCassino = 'Promoção cassino';

    /**
     * Coluna da tabela clientes que guarda o valor desta carteira.
     */
    public function coluna(): string
    {
        return match ($this) {
            self::Saldo => 'saldo',
            self::PromoçãoEsportes => 'saldo_promocao_esportes',
            self::PromoçãoCassino => 'saldo_promocao_cassino',
        };
    }
}
