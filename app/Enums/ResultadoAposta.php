<?php

namespace App\Enums;

/**
 * Resultado de uma aposta; nasce Aguardando e só muda na apuração (outra spec).
 */
enum ResultadoAposta: string
{
    case Aguardando = 'Aguardando';
    case Vencedor = 'Vencedor';
    case Perdedor = 'Perdedor';
}
