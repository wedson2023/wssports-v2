<?php

namespace App\Enums;

/**
 * Tipo da aposta: com pelo menos um jogo do ao vivo, a aposta inteira é Ao vivo.
 */
enum TipoAposta: string
{
    case PréJogo = 'Pré-jogo';
    case AoVivo = 'Ao vivo';
}
