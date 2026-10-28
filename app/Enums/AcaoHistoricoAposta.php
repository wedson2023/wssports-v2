<?php

namespace App\Enums;

/**
 * Ação registrada no histórico de edição de uma aposta.
 */
enum AcaoHistoricoAposta: string
{
    case CancelarPalpite = 'Cancelar palpite';
    case RestaurarPalpite = 'Restaurar palpite';
    // cotação do palpite especial dividida (ou desfeita) pelo empate na última vaga (spec 024)
    case DivisãoEmpate = 'Divisão por empate';
}
