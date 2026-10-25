<?php

namespace App\Enums;

/**
 * Situação de um bônus recebido (spec 020, FR-021, R-03); calculada, não gravada.
 */
enum SituacaoBonus: string
{
    case EmAndamento = 'Em andamento';
    case Cumprido = 'Cumprido';
    case Convertido = 'Convertido';
    case Expirado = 'Expirado';
    case ExcluídoPeloCliente = 'Excluído pelo cliente';
    case Estornado = 'Estornado';

    /**
     * Só o bônus que ainda tem saldo a retirar (em andamento ou cumprido e não convertido) pode ser
     * estornado.
     */
    public function estornavel(): bool
    {
        return in_array($this, [self::EmAndamento, self::Cumprido], true);
    }
}
