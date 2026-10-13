<?php

namespace App\Enums;

/**
 * Situação do vínculo da conta da banca no gateway de pagamento.
 */
enum SituacaoGateway: string
{
    case Vinculado = 'Vinculado';
    case FalhaRenovação = 'Falha na renovação';
    case Desvinculado = 'Desvinculado';
}
