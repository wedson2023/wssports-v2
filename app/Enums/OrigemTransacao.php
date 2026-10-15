<?php

namespace App\Enums;

/**
 * O que gerou a movimentação de saldo.
 */
enum OrigemTransacao: string
{
    case AjusteManual = 'Ajuste manual';
    case Promoção = 'Promoção';
    case Aposta = 'Aposta';
    case Prêmio = 'Prêmio';
    case Estorno = 'Estorno';
    // pagamentos do painel do cliente (spec 007)
    case Depósito = 'Depósito';
    case Cashback = 'Cashback';
    case Saque = 'Saque';
    case DevoluçãoSaque = 'Devolução de saque';
    case EstornoDepósito = 'Estorno de depósito';
    case ConversãoBônus = 'Conversão de bônus';
    case BônusExpirado = 'Bônus expirado';
    case BônusExcluído = 'Bônus excluído';
    // apuração (spec 009): valor devolvido quando todos os palpites da aposta foram cancelados
    case DevoluçãoAposta = 'Devolução de aposta';
}
