<?php

namespace App\Enums;

/**
 * Até que dia o público vê e aposta nos jogos do pré-jogo.
 */
enum PeriodoJogos: string
{
    case Hoje = 'Hoje';
    case Amanhã = 'Amanhã';
    case DepoisDeAmanhã = 'Depois de amanhã';

    /**
     * Quantos dias à frente de hoje o período alcança.
     */
    public function dias_a_frente(): int
    {
        return match ($this) {
            self::Hoje => 0,
            self::Amanhã => 1,
            self::DepoisDeAmanhã => 2,
        };
    }
}
