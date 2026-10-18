<?php

namespace App\Enums;

/**
 * Base da comissão do afiliado: depósitos menos saques (Saldo líquido), todo depósito aprovado
 * (Qualquer depósito) ou só o primeiro depósito aprovado do cliente em toda a vida da conta
 * (Primeiro depósito).
 */
enum TipoGanhoAfiliado: string
{
    case SaldoLíquido = 'Saldo líquido';
    case QualquerDepósito = 'Qualquer depósito';
    case PrimeiroDepósito = 'Primeiro depósito';

    /**
     * Só no Saldo líquido o saque pago do cliente desconta da comissão.
     */
    public function desconta_saque(): bool
    {
        return $this === self::SaldoLíquido;
    }
}
