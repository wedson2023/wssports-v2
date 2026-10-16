<?php

namespace App\Enums;

/**
 * Situação de uma carga do catálogo do cassino (spec 011, FR-005).
 */
enum SituacaoSincronizacaoCassino: string
{
    case EmAndamento = 'Em andamento';
    case Concluída = 'Concluída';
    case Falha = 'Falha';
}
