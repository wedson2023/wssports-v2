<?php

namespace App\Enums;

/**
 * Situação de um jogo em andamento; os valores seguem o provedor e o sistema antigo.
 */
enum SituacaoAoVivo: string
{
    case PrimeiroTempo = '1 tempo';
    case Intervalo = 'Intervalo';
    case SegundoTempo = '2 tempo';
}
