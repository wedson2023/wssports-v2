<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Models\CassinosSessoes;
use App\Models\Clientes;

/**
 * Carteira da sessão de jogo do cassino (spec 011, FR-020, FR-024, R-17), no padrão do
 * EscolhaCarteira das apostas: uma carteira por vez, nunca as duas juntas numa aposta. A ordem é
 * saldo real e depois bônus de cassino; nos canais "bônus primeiro", o inverso.
 */
class EscolhaCarteiraCassino
{
    /**
     * @return list<Carteira>
     */
    public function ordem(?CassinosSessoes $sessao): array
    {
        return $sessao?->bonus_primeiro
            ? [Carteira::PromoçãoCassino, Carteira::Saldo]
            : [Carteira::Saldo, Carteira::PromoçãoCassino];
    }

    /**
     * Carteira que paga a aposta: a da sessão, se cobre o valor; senão, a primeira da ordem que cobre.
     *
     * @param  Clientes  $cliente  já bloqueado na transação, com os saldos atuais
     * @return Carteira|null null quando nenhuma carteira cobre o valor inteiro
     */
    public function escolher(Clientes $cliente, CassinosSessoes $sessao, string $valor): ?Carteira
    {
        $candidatas = $sessao->carteira ? [$sessao->carteira, ...$this->ordem($sessao)] : $this->ordem($sessao);

        foreach ($candidatas as $carteira) {
            if (bccomp((string) $cliente->getRawOriginal($carteira->coluna()), $valor, 2) >= 0) {
                return $carteira;
            }
        }

        return null;
    }

    /**
     * Carteira informada na consulta de saldo: a da sessão; antes da primeira aposta, a primeira da
     * ordem com saldo maior que zero; sem saldo em nenhuma, o saldo real.
     */
    public function carteira_saldo(Clientes $cliente, ?CassinosSessoes $sessao): Carteira
    {
        if ($sessao?->carteira) {
            return $sessao->carteira;
        }

        foreach ($this->ordem($sessao) as $carteira) {
            if (bccomp((string) $cliente->getRawOriginal($carteira->coluna()), '0', 2) > 0) {
                return $carteira;
            }
        }

        return Carteira::Saldo;
    }
}
