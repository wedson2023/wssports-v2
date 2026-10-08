<?php

namespace App\Enums;

/**
 * Ação registrada no histórico de edição de uma aposta.
 */
enum AcaoHistoricoAposta: string
{
    case CancelarPalpite = 'Cancelar palpite';
    case RestaurarPalpite = 'Restaurar palpite';
}
