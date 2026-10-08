<?php

namespace App\Enums;

/**
 * Como a aposta foi paga: em dinheiro (vendedor) ou com uma carteira do cliente.
 */
enum FormaPagamento: string
{
    case Dinheiro = 'Dinheiro';
    case Saldo = 'Saldo';
    case PromoçãoEsportes = 'Promoção esportes';

    /**
     * Carteira do cliente usada; null quando paga em dinheiro.
     */
    public function carteira(): ?Carteira
    {
        return match ($this) {
            self::Dinheiro => null,
            self::Saldo => Carteira::Saldo,
            self::PromoçãoEsportes => Carteira::PromoçãoEsportes,
        };
    }

    public static function da_carteira(Carteira $carteira): self
    {
        return $carteira === Carteira::Saldo ? self::Saldo : self::PromoçãoEsportes;
    }
}
