<?php

namespace App\Services;

/**
 * Prêmio da aposta (FR-025), sem ponto flutuante: produto das cotações com bcmath em 10 casas,
 * prêmio truncado em centavos (nunca arredonda a favor do apostador).
 */
class CalculoPremio
{
    private const ESCALA = 10;

    /**
     * Quantidade mínima de palpites para o acréscimo por múltiplos palpites.
     */
    private const MINIMO_PALPITES_ACRESCIMO = 3;

    /**
     * @param  list<string>  $cotacoes  cotações dos palpites ativos, em texto com 2 casas
     * @return array{cotacao_total: string, premio: string, valor_acrescido: string, total_a_pagar: string}
     */
    public function calcular(string $valor, array $cotacoes, int $multiplicador, string $premio_maximo, string $ganho_multiplo_palpites): array
    {
        $produto = '1';

        foreach ($cotacoes as $cotacao) {
            $produto = bcmul($produto, $cotacao, self::ESCALA);
        }

        // prêmio = menor entre valor × cotações, valor × multiplicador e o prêmio máximo
        $premio = $this->menor([
            bcmul($valor, $produto, 2),
            bcmul($valor, (string) $multiplicador, 2),
            bcadd($premio_maximo, '0', 2),
        ]);

        $acrescimo = $this->acrescimo($premio, count($cotacoes), $premio_maximo, $ganho_multiplo_palpites);

        return [
            'cotacao_total' => $this->arredondar($produto),
            'premio' => $premio,
            'valor_acrescido' => $acrescimo,
            'total_a_pagar' => bcadd($premio, $acrescimo, 2),
        ];
    }

    /**
     * Acréscimo de ganho_multiplo_palpites% com 3 ou mais palpites, reduzido para que prêmio +
     * acréscimo nunca passe do prêmio máximo.
     */
    private function acrescimo(string $premio, int $quantidade, string $premio_maximo, string $ganho): string
    {
        if ($quantidade < self::MINIMO_PALPITES_ACRESCIMO || bccomp($ganho, '0', 2) <= 0) {
            return '0.00';
        }

        $acrescimo = bcdiv(bcmul($premio, $ganho, self::ESCALA), '100', 2);
        $folga = bcsub($premio_maximo, $premio, 2);

        return $this->menor([$acrescimo, bccomp($folga, '0', 2) > 0 ? $folga : '0.00']);
    }

    /**
     * @param  list<string>  $valores
     */
    private function menor(array $valores): string
    {
        return array_reduce($valores, fn (?string $menor, string $valor) => $menor === null || bccomp($valor, $menor, 2) < 0 ? $valor : $menor);
    }

    /**
     * Arredonda em 2 casas, meio para cima (só para gravar e exibir a cotação total).
     */
    private function arredondar(string $numero): string
    {
        return bcadd($numero, '0.005', 2);
    }
}
