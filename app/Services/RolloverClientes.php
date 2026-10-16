<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\FormaPagamento;
use App\Enums\TipoRollover;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasRollovers;
use App\Models\CassinosRodadas;
use App\Models\CassinosRodadasRollovers;
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
            // prazo para cumprir; vencido, o bônus é retirado (spec 007, FR-018a)
            'expira_em' => now()->addDays($promocao->dias_cumprir ?? 30),
        ]);
    }

    /**
     * Rollover de um depósito (spec 007, R-11): exigido = valor × vezes + acréscimo (cashback);
     * não cria nada quando o exigido é zero.
     */
    public function criar_deposito(Clientes $cliente, ClientesTransacoes $transacao, int $vezes, string $acrescimo = '0.00'): ?ClientesRollovers
    {
        $exigido = bcadd(bcmul((string) $transacao->valor, (string) $vezes, 2), $acrescimo, 2);

        if (bccomp($exigido, '0', 2) <= 0) {
            return null;
        }

        return ClientesRollovers::create([
            'clientes_id' => $cliente->id,
            'tipo' => TipoRollover::Depósito,
            'carteira' => Carteira::Saldo,
            'clientes_transacoes_id' => $transacao->id,
            'valor_creditado' => $transacao->valor,
            'vezes' => $vezes,
            'valor_exigido' => $exigido,
        ]);
    }

    /**
     * Rollover que deixa de valer (estorno do depósito ou do bônus).
     */
    public function cancelar(ClientesRollovers $rollover): void
    {
        if ($rollover->cumprido_em === null && $rollover->cancelado_em === null) {
            $rollover->forceFill(['cancelado_em' => now()])->save();
        }
    }

    /**
     * Quanto falta apostar nos rollovers de depósito pendentes do cliente (trava do saque).
     */
    public function restante_deposito(int $clientes_id): string
    {
        return ClientesRollovers::where('clientes_id', $clientes_id)
            ->where('tipo', TipoRollover::Depósito)
            ->pendentes()
            ->get()
            ->reduce(fn (string $total, ClientesRollovers $rollover) => bcadd($total, $rollover->restante(), 2), '0.00');
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
     * Encerramento de aposta (spec 010, FR-038): retira do progresso de cada rollover a parte
     * encerrada (o que a aposta ainda soma × proporção) e reduz a ligação na mesma medida, para um
     * cancelamento posterior desfazer só o que restou. Devolve o que saiu de cada rollover, para o
     * encerramento desfeito poder devolver.
     *
     * @return list<array{clientes_rollovers_id: int, valor: string}>
     */
    public function retirar(Apostas $aposta, string $proporcao): array
    {
        $ligacoes = ApostasRollovers::where('apostas_id', $aposta->id)
            ->whereNull('desfeito_em')
            ->orderBy('clientes_rollovers_id')
            ->get();

        $retiradas = [];

        foreach ($ligacoes as $ligacao) {
            $valor = bcmul((string) $ligacao->valor, $proporcao, 2);

            if (bccomp($valor, '0', 2) <= 0) {
                continue;
            }

            $rollover = ClientesRollovers::lockForUpdate()->find($ligacao->clientes_rollovers_id);

            if ($rollover !== null) {
                $rollover->valor_apostado = bcsub((string) $rollover->valor_apostado, $valor, 2);
                $rollover->cumprido_em = bccomp((string) $rollover->valor_apostado, (string) $rollover->valor_exigido, 2) >= 0 ? $rollover->cumprido_em : null;
                $rollover->save();
            }

            $ligacao->valor = bcsub((string) $ligacao->valor, $valor, 2);
            $ligacao->save();

            $retiradas[] = ['clientes_rollovers_id' => (int) $ligacao->clientes_rollovers_id, 'valor' => $valor];
        }

        return $retiradas;
    }

    /**
     * Encerramento desfeito (spec 010, FR-038): o que foi retirado volta ao progresso e à ligação.
     *
     * @param  list<array{clientes_rollovers_id: int, valor: string}>  $retiradas
     */
    public function devolver(array $retiradas, int $apostas_id): void
    {
        foreach ($retiradas as $retirada) {
            $rollover = ClientesRollovers::lockForUpdate()->find($retirada['clientes_rollovers_id']);

            if ($rollover !== null) {
                $rollover->valor_apostado = bcadd((string) $rollover->valor_apostado, $retirada['valor'], 2);
                $rollover->cumprido_em = bccomp((string) $rollover->valor_apostado, (string) $rollover->valor_exigido, 2) >= 0
                    ? ($rollover->cumprido_em ?? now())
                    : null;
                $rollover->save();
            }

            $ligacao = ApostasRollovers::where('apostas_id', $apostas_id)
                ->where('clientes_rollovers_id', $retirada['clientes_rollovers_id'])
                ->first();

            if ($ligacao !== null) {
                $ligacao->valor = bcadd((string) $ligacao->valor, $retirada['valor'], 2);
                $ligacao->save();
            }
        }
    }

    /**
     * Cassino (spec 011, FR-025, R-18): a aposta de uma rodada soma nos rollovers pendentes da
     * carteira usada (saldo real → Depósito; bônus de cassino → Bônus de cassino), do mais antigo para
     * o mais novo, com a ligação para o cancelamento desfazer.
     */
    public function somar_cassino(CassinosRodadas $rodada): void
    {
        [$tipo, $carteira] = $rodada->carteira === Carteira::PromoçãoCassino
            ? [TipoRollover::Bônus, Carteira::PromoçãoCassino]
            : [TipoRollover::Depósito, Carteira::Saldo];

        $restante = (string) $rodada->valor_aposta;

        $pendentes = ClientesRollovers::where('clientes_id', $rodada->clientes_id)
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

            CassinosRodadasRollovers::create([
                'cassinos_rodadas_id' => $rodada->id,
                'clientes_rollovers_id' => $rollover->id,
                'valor' => $usado,
            ]);

            $restante = bcsub($restante, $usado, 2);
        }
    }

    /**
     * Cassino (spec 011, R-18): desfaz exatamente o que a aposta da rodada somou (cancelamento ou
     * reembolso pelo provedor).
     */
    public function desfazer_cassino(CassinosRodadas $rodada): void
    {
        $ligacoes = CassinosRodadasRollovers::where('cassinos_rodadas_id', $rodada->id)
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
