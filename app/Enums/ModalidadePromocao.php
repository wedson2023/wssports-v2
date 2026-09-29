<?php

namespace App\Enums;

enum ModalidadePromocao: string
{
    case Esportes = 'Esportes';
    case Cassino = 'Cassino';

    /**
     * Saldo promocional que recebe o bônus desta modalidade.
     */
    public function carteira(): Carteira
    {
        return match ($this) {
            self::Esportes => Carteira::PromoçãoEsportes,
            self::Cassino => Carteira::PromoçãoCassino,
        };
    }
}
