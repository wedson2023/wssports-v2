<?php

namespace App\Services;

use App\Enums\FormaPagamento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Clientes;
use Illuminate\Support\Collection;

/**
 * Critérios para a aposta poder ser encerrada (spec 010, FR-017, R-05), na ordem da research, com o
 * motivo exibido no bilhete quando falha (FR-023). O mínimo da oferta (critério 11) fica no
 * CalculoEncerramento, que é quem calcula a oferta.
 */
class ElegibilidadeEncerramento
{
    public function __construct(private ConfiguracoesEncerramento $configuracoes) {}

    /**
     * @param  Collection<int, ApostasPalpites>  $palpites  todos os palpites da aposta
     * @param  array<int, array<string, mixed>>  $cotacoes  situação dos palpites em aberto (CalculoEncerramento::cotacoes_atuais)
     * @return array{elegivel: bool, motivo: ?string}
     */
    public function avaliar(Apostas $aposta, Collection $palpites, array $cotacoes): array
    {
        $motivo = $this->motivo($aposta, $palpites, $cotacoes);

        return ['elegivel' => $motivo === false, 'motivo' => $motivo === false ? null : $motivo];
    }

    /**
     * Primeiro critério que falha; null quando a aposta já está no estado final (sem bloco de
     * encerramento) e false quando passou em todos.
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @param  array<int, array<string, mixed>>  $cotacoes
     */
    private function motivo(Apostas $aposta, Collection $palpites, array $cotacoes): string|null|false
    {
        if (! $this->configuracoes->habilitado()) {
            return 'Encerramento de apostas indisponível no momento.';
        }

        if ($aposta->clientes_id === null || $aposta->forma_pagamento !== FormaPagamento::Saldo) {
            return 'Esta aposta não pode ser encerrada.';
        }

        if ($palpites->contains(fn (ApostasPalpites $palpite) => $palpite->apostas_blocos_id !== null)) {
            return 'Apostas com Criar Aposta não podem ser encerradas.';
        }

        $cliente = Clientes::withTrashed()->find($aposta->clientes_id);

        if ($cliente === null || ! $cliente->pode_acessar() || ! $this->configuracoes->cliente_pode($cliente)) {
            return 'Encerramento indisponível para a sua conta.';
        }

        if ($aposta->situacao !== SituacaoAposta::Ativa || $aposta->resultado !== ResultadoAposta::Aguardando) {
            return null;
        }

        $ativos = $palpites->filter(fn (ApostasPalpites $palpite) => $palpite->situacao === SituacaoPalpite::Ativo);

        if ($ativos->contains(fn (ApostasPalpites $palpite) => $palpite->resultado === ResultadoAposta::Perdedor)) {
            return 'Um palpite da aposta já está perdido.';
        }

        $algum_iniciado = $ativos->contains(fn (ApostasPalpites $palpite) => $palpite->resultado !== ResultadoAposta::Aguardando)
            || collect($cotacoes)->contains(fn (array $situacao) => $situacao['iniciado']);

        if (! $this->configuracoes->jogos_nao_iniciados() && ! $algum_iniciado) {
            return 'O encerramento fica disponível quando o primeiro jogo começar.';
        }

        foreach ($cotacoes as $situacao) {
            if ($situacao['com_resultado']) {
                return "Aguardando a apuração de {$situacao['nome_jogo']}.";
            }
        }

        foreach ($cotacoes as $situacao) {
            if ($situacao['travado']) {
                return "Jogo travado em {$situacao['nome_jogo']}.";
            }
        }

        foreach ($cotacoes as $situacao) {
            if ($situacao['cotacao_atual'] === null) {
                return "Mercado suspenso em {$situacao['nome_jogo']}.";
            }
        }

        return false;
    }
}
