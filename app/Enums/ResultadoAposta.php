<?php

namespace App\Enums;

/**
 * Resultado de uma aposta ou de um palpite; nasce Aguardando e só muda na apuração (spec 009).
 * Só o palpite usa Devolvido (empate anula aposta: vale 1,00).
 */
enum ResultadoAposta: string
{
    case Aguardando = 'Aguardando';
    case Vencedor = 'Vencedor';
    case Perdedor = 'Perdedor';
    case Devolvido = 'Devolvido';
}
