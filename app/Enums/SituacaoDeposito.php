<?php

namespace App\Enums;

/**
 * Situação do depósito: Pendente (aguardando pagamento) e Em análise (cartão) podem mudar;
 * Recusado, Expirado, Cancelado e Estornado são finais.
 */
enum SituacaoDeposito: string
{
    case Pendente = 'Pendente';
    case EmAnálise = 'Em análise';
    case Aprovado = 'Aprovado';
    case Recusado = 'Recusado';
    case Expirado = 'Expirado';
    case Cancelado = 'Cancelado';
    case Estornado = 'Estornado';

    public function final(): bool
    {
        return in_array($this, [self::Recusado, self::Expirado, self::Cancelado, self::Estornado], true);
    }
}
