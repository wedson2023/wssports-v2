<?php

namespace App\Enums;

/**
 * Situação do encerramento automático por valor-alvo (spec 010).
 */
enum SituacaoEncerramentoAutomatico: string
{
    case Ativo = 'Ativo';
    case Executado = 'Executado';
    case Cancelado = 'Cancelado';
}
