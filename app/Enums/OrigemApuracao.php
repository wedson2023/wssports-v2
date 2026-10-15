<?php

namespace App\Enums;

/**
 * O que disparou uma operação da apuração (spec 009, FR-037).
 */
enum OrigemApuracao: string
{
    case CargaResultados = 'Carga de resultados';
    case CargaCancelados = 'Carga de cancelados';
    case PagamentoPrêmios = 'Pagamento de prêmios';
    case ReprocessamentoManual = 'Reprocessamento manual';
    case LançamentoManual = 'Lançamento manual';
    case EncerramentoEspecial = 'Encerramento de especial';
}
