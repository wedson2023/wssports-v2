<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Exceptions\RegraApostaException;
use App\Models\Clientes;

/**
 * Carteira que paga a aposta esportiva do cliente (R-14, FR-043): o saldo real sempre primeiro;
 * sem ele, o bônus de esportes; nunca as duas juntas e nunca o bônus de cassino.
 */
class EscolhaCarteira
{
    /**
     * @param  Clientes  $cliente  já bloqueado na transação, com os saldos atuais
     *
     * @throws RegraApostaException quando nenhuma carteira cobre o valor inteiro
     */
    public function escolher(Clientes $cliente, string $valor): Carteira
    {
        foreach ([Carteira::Saldo, Carteira::PromoçãoEsportes] as $carteira) {
            if (bccomp((string) $cliente->getRawOriginal($carteira->coluna()), $valor, 2) >= 0) {
                return $carteira;
            }
        }

        throw new RegraApostaException('Você não tem saldo suficiente para realizar esta aposta.');
    }
}
