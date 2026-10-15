<?php

namespace App\Enums;

/**
 * Tipo de cada operação registrada pela apuração (spec 009, FR-036).
 */
enum TipoOperacaoApuracao: string
{
    case ResultadoJogo = 'Resultado do jogo';
    case SituaçãoJogo = 'Situação do jogo';
    case ResultadoPalpite = 'Resultado do palpite';
    case ResultadoAposta = 'Resultado da aposta';
    case PalpiteCancelado = 'Palpite cancelado';
    case PalpiteRestaurado = 'Palpite restaurado';
    case PrêmioPago = 'Prêmio pago';
    case PrêmioAjustado = 'Prêmio ajustado';
    case PrêmioEstornado = 'Prêmio estornado';
    case ValorDevolvido = 'Valor devolvido';
}
