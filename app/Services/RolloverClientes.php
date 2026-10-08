<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\FormaPagamento;
use App\Enums\TipoRollover;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasRollovers;
use App\Models\Clientes;
use App\Models\ClientesPromocoes;
use App\Models\ClientesRollovers;
use App\Models\ClientesTransacoes;

/**
 * Rollover do cliente (R-13): o valor apostado soma nos créditos pendentes, do mais antigo para o
 * mais novo, e o cancelamento desfaz exatamente o que a aposta somou.
 */
class RolloverClientes
{
    /**
     * Registro de rollover de um bônus recebido; não cria nada quando a promoção não exige
     * rollover.
     */
    public function criar_bonus(Clientes $cliente, ClientesTransacoes $transacao, ClientesPromocoes $promocao): ?ClientesRollovers
    {
        if ((int) $promocao->rollover <= 0) {
            return null;
        }

        return ClientesRollovers::create([
            'clientes_id' => $cliente->id,
            'tipo' => TipoRollover::Bônus,
            'carteira' => $promocao->modalidade->carteira(),
            'clientes_transacoes_id' => $transacao->id,
            'clientes_promocoes_id' => $promocao->id,
            'valor_creditado' => $transacao->valor,
            'vezes' => $promocao->rollover,
            'valor_exigido' => bcmul((string) $transacao->valor, (string) $promocao->rollover, 2),
            'valor_minimo_aposta' => $promocao->valor_minimo_aposta,
            'valor_maximo_aposta' => $promocao->valor_maximo_aposta,
            'odd_minima_aposta_simples' => $promocao->odd_minima_aposta_simples,
            'odd_minima_aposta_multipla' => $promocao->odd_minima_aposta_multipla,
        ]);
    }

    /**
     * Regras do bônus de esportes pendente mais antigo, quando a aposta é paga com ele (FR-045).
     *
     * @throws RegraApostaException
     */
    public function conferir_regras_bonus(Clientes $cliente, string $valor, int $quantidade_palpites, string $cotacao_total): void
    {
        $bonus = ClientesRollovers::where('clientes_id', $cliente->id)
            ->where('tipo', TipoRollover::Bônus)
            ->where('carteira', Carteira::PromoçãoEsportes)
            ->pendentes()
            ->first();

        if ($bonus === null) {
            return;
        }

        if ($bonus->valor_minimo_aposta !== null && bccomp($valor, (string) $bonus->valor_minimo_aposta, 2) < 0) {
            throw new RegraApostaException('Para apostas usando saldo do bônus o valor mínimo é '.RegrasAposta::reais((string) $bonus->valor_minimo_aposta).'.');
        }

        if ($bonus->valor_maximo_aposta !== null && bccomp($valor, (string) $bonus->valor_maximo_aposta, 2) > 0) {
            throw new RegraApostaException('Para apostas usando saldo do bônus o valor máximo é '.RegrasAposta::reais((string) $bonus->valor_maximo_aposta).'.');
        }

        [$odd_minima, $tipo] = $quantidade_palpites === 1
            ? [$bonus->odd_minima_aposta_simples, 'simples']
            : [$bonus->odd_minima_aposta_multipla, 'múltiplas'];

        if ($odd_minima !== null && bccomp($cotacao_total, (string) $odd_minima, 2) < 0) {
            throw new RegraApostaException("Para apostas {$tipo} usando saldo do bônus a cotação mínima é ".RegrasAposta::cotacao((string) $odd_minima).'.');
        }
    }

    /**
     * Soma o valor da aposta confirmada nos rollovers pendentes: saldo real → Depósito;
     * bônus de esportes → Bônus de esportes.
     */
    public function somar(Apostas $aposta): void
    {
        [$tipo, $carteira] = $aposta->forma_pagamento === FormaPagamento::Saldo
            ? [TipoRollover::Depósito, Carteira::Saldo]
            : [TipoRollover::Bônus, Carteira::PromoçãoEsportes];

        $restante = (string) $aposta->valor;

        $pendentes = ClientesRollovers::where('clientes_id', $aposta->clientes_id)
            ->where('tipo', $tipo)
            ->where('carteira', $carteira)
            ->pendentes()
            ->lockForUpdate()
            ->get();

        foreach ($pendentes as $rollover) {
            if (bccomp($restante, '0', 2) <= 0) {
                break;
            }

            $falta = bcsub((string) $rollover->valor_exigido, (string) $rollover->valor_apostado, 2);
            $usado = bccomp($restante, $falta, 2) < 0 ? $restante : $falta;

            if (bccomp($usado, '0', 2) <= 0) {
                continue;
            }

            $rollover->valor_apostado = bcadd((string) $rollover->valor_apostado, $usado, 2);
            $rollover->cumprido_em = bccomp((string) $rollover->valor_apostado, (string) $rollover->valor_exigido, 2) >= 0 ? now() : null;
            $rollover->save();

            ApostasRollovers::create([
                'apostas_id' => $aposta->id,
                'clientes_rollovers_id' => $rollover->id,
                'valor' => $usado,
            ]);

            $restante = bcsub($restante, $usado, 2);
        }
    }

    /**
     * Desfaz exatamente o que a aposta somou (cancelamento).
     */
    public function desfazer(Apostas $aposta): void
    {
        $ligacoes = ApostasRollovers::where('apostas_id', $aposta->id)
            ->whereNull('desfeito_em')
            ->orderBy('clientes_rollovers_id')
            ->get();

        foreach ($ligacoes as $ligacao) {
            $rollover = ClientesRollovers::lockForUpdate()->find($ligacao->clientes_rollovers_id);

            if ($rollover !== null) {
                $rollover->valor_apostado = bcsub((string) $rollover->valor_apostado, (string) $ligacao->valor, 2);
                $rollover->cumprido_em = null;
                $rollover->save();
            }

            $ligacao->forceFill(['desfeito_em' => now()])->save();
        }
    }

    /**
     * Promoção estornada: os rollovers pendentes dela deixam de valer.
     */
    public function cancelar_da_promocao(int $clientes_id, int $clientes_promocoes_id): void
    {
        ClientesRollovers::where('clientes_id', $clientes_id)
            ->where('clientes_promocoes_id', $clientes_promocoes_id)
            ->whereNull('cumprido_em')
            ->whereNull('cancelado_em')
            ->update(['cancelado_em' => now()]);
    }
}
