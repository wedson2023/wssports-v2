<?php

namespace App\Enums;

/**
 * Efeito no saldo de um tipo de ação do lançamento de rodada do provedor do cassino (spec 011, R-16).
 */
enum EfeitoAcaoCassino: string
{
    // debita a aposta (e credita o prêmio, se vier)
    case Aposta = 'Aposta';
    // credita o prêmio; aposta maior que zero é recusada
    case Prêmio = 'Prêmio';
    // debita a aposta e credita o prêmio num movimento só
    case ApostaComPrêmio = 'Aposta com prêmio';
    // ignora a aposta e credita o prêmio
    case RodadaGrátis = 'Rodada grátis';
    // devolve a aposta à carteira de origem
    case Cancelamento = 'Cancelamento';
    // devolve a aposta sem conferir saldo
    case Reembolso = 'Reembolso';

    /**
     * O lançamento debita o valor apostado.
     */
    public function debita_aposta(): bool
    {
        return $this === self::Aposta || $this === self::ApostaComPrêmio;
    }

    /**
     * O lançamento devolve uma aposta feita antes.
     */
    public function devolve_aposta(): bool
    {
        return $this === self::Cancelamento || $this === self::Reembolso;
    }
}
