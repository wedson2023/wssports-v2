<?php

namespace App\Services;

/**
 * Cotação do bloco Criar Aposta e "pernas" da aposta (spec 008, R-04): os palpites de um mesmo jogo
 * do pré-jogo formam um bloco, que entra na aposta como um jogo só. Sem ponto flutuante: bcmath com
 * a cotação do bloco truncada em 2 casas (nunca a favor do apostador) e nunca menor que 1,00.
 */
class CalculoBlocos
{
    private const ESCALA = 10;

    private const COTACAO_MINIMA = '1.00';

    /**
     * Produto das cotações × (1 − desconto ÷ 100), truncado em 2 casas. Com um único palpite não há
     * correlação: vale a cotação dele, sem desconto.
     *
     * @param  list<string>  $cotacoes
     */
    public function cotacao_bloco(array $cotacoes, string $desconto): string
    {
        if (count($cotacoes) === 1) {
            return bcadd($cotacoes[0], '0', 2);
        }

        $produto = '1';

        foreach ($cotacoes as $cotacao) {
            $produto = bcmul($produto, $cotacao, self::ESCALA);
        }

        $com_desconto = bcdiv(bcmul($produto, bcsub('100', $desconto, self::ESCALA), self::ESCALA), '100', self::ESCALA);
        $truncada = bcadd($com_desconto, '0', 2);

        return bccomp($truncada, self::COTACAO_MINIMA, 2) < 0 ? self::COTACAO_MINIMA : $truncada;
    }

    /**
     * Pernas da aposta, na ordem do primeiro palpite de cada uma: palpite simples ou bloco (dois ou
     * mais palpites do mesmo jogo do pré-jogo). Ao vivo e especial são sempre palpite simples.
     *
     * @param  list<array<string, mixed>>  $palpites  com confrontos_id, confrontos_ao_vivo_id, especiais_id e a cotação
     * @param  string  $chave_cotacao  cotacao_atual (palpites montados) ou cotacao_final (gravados)
     * @return list<array{tipo: string, confrontos_id: int|null, indices: list<int>, cotacao: string}>
     */
    public function pernas(array $palpites, string $desconto, string $chave_cotacao = 'cotacao_atual'): array
    {
        $grupos = [];

        foreach (array_values($palpites) as $indice => $palpite) {
            $do_pre_jogo = ($palpite['confrontos_ao_vivo_id'] ?? null) === null
                && ($palpite['especiais_id'] ?? null) === null
                && ($palpite['confrontos_id'] ?? null) !== null;

            $chave = $do_pre_jogo ? "confronto:{$palpite['confrontos_id']}" : "palpite:{$indice}";
            $grupos[$chave][] = $indice;
        }

        $pernas = [];
        $palpites = array_values($palpites);

        foreach ($grupos as $indices) {
            $cotacoes = array_map(fn (int $indice) => (string) $palpites[$indice][$chave_cotacao], $indices);
            $bloco = count($indices) > 1;

            $pernas[] = [
                'tipo' => $bloco ? 'bloco' : 'palpite',
                'confrontos_id' => $palpites[$indices[0]]['confrontos_id'] ?? null,
                'indices' => $indices,
                'cotacao' => $bloco ? $this->cotacao_bloco($cotacoes, $desconto) : bcadd($cotacoes[0], '0', 2),
            ];
        }

        return $pernas;
    }

    /**
     * Cotações das pernas, na ordem, para o CalculoPremio (o bloco conta como um jogo).
     *
     * @param  list<array{cotacao: string}>  $pernas
     * @return list<string>
     */
    public static function cotacoes(array $pernas): array
    {
        return array_column($pernas, 'cotacao');
    }

    /**
     * Só os blocos das pernas.
     *
     * @param  list<array<string, mixed>>  $pernas
     * @return list<array<string, mixed>>
     */
    public static function blocos(array $pernas): array
    {
        return array_values(array_filter($pernas, fn (array $perna) => $perna['tipo'] === 'bloco'));
    }
}
