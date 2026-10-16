<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha numa chamada ao provedor do cassino (spec 011, R-08). Guarda o código de erro devolvido pelo
 * provedor, ou nulo em tempo esgotado e falha de rede. A mensagem é a que o cliente pode ver.
 */
class FalhaProvedorCassinoException extends RuntimeException
{
    /**
     * Erros com que o provedor recusa abrir o jogo por um canal: jogo não ativo no hall e crédito do
     * hall insuficiente (canal pré-pago).
     */
    private const RECUSAS_CANAL = ['games_not_activ', 'hall_balance_less_100'];

    public function __construct(public readonly ?string $codigo_provedor = null, string $mensagem = 'Não foi possível abrir o jogo. Tente novamente.')
    {
        parent::__construct($mensagem);
    }

    /**
     * O provedor recusou o canal (o próximo canal do jogo pode ser tentado).
     */
    public function recusou_canal(): bool
    {
        return in_array($this->codigo_provedor, self::RECUSAS_CANAL, true);
    }
}
