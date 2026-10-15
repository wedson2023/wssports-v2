<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\FormaPagamento;
use App\Enums\OrigemApuracao;
use App\Enums\OrigemTransacao;
use App\Enums\ResultadoAposta;
use App\Enums\TipoOperacaoApuracao;
use App\Models\Apostas;
use App\Models\Clientes;
use App\Models\ClientesTransacoes;
use App\Models\Configuracoes;

/**
 * Pagamento dos prêmios (spec 009, R-10): movimenta só a diferença entre o valor devido e o já pago
 * (apostas.valor_pago), sempre pelo SaldoClientes. Crédito depois da janela de segurança; estorno
 * na hora, podendo negativar o saldo. Nenhuma transação é apagada e nenhum saldo é sobrescrito.
 */
class LiquidacaoApostas
{
    private ?int $minutos_janela = null;

    public function __construct(
        private SaldoClientes $saldo,
        private RegistroApuracao $registro,
    ) {}

    /**
     * Deve rodar dentro da transação de quem chama, com a aposta já bloqueada.
     */
    public function liquidar(Apostas $aposta, OrigemApuracao $origem): void
    {
        if (! $this->elegivel($aposta)) {
            $this->marcar_pendente($aposta, false);

            return;
        }

        $devido = SaldoClientes::para_centavos($this->devido($aposta));
        $pago = SaldoClientes::para_centavos((string) $aposta->valor_pago);
        $diferenca = $devido - $pago;

        if ($diferenca === 0) {
            $this->marcar_pendente($aposta, false);

            return;
        }

        // crédito espera a janela, para absorver correções rápidas do provedor; estorno é na hora
        if ($diferenca > 0 && ! $this->janela_vencida($aposta)) {
            $this->marcar_pendente($aposta, true);

            return;
        }

        $diferenca > 0
            ? $this->creditar($aposta, $origem, $diferenca, $pago)
            : $this->estornar($aposta, $origem, -$diferenca, $devido);

        $aposta->forceFill([
            'valor_pago' => SaldoClientes::para_decimal($devido),
            'liquidacao_pendente' => false,
        ])->save();
    }

    /**
     * Só aposta de cliente paga com uma carteira recebe no saldo; a do vendedor é paga em dinheiro.
     */
    public function elegivel(Apostas $aposta): bool
    {
        return $aposta->clientes_id !== null
            && in_array($aposta->forma_pagamento, [FormaPagamento::Saldo, FormaPagamento::PromoçãoEsportes], true);
    }

    /**
     * Prêmio + acréscimo quando Vencedor; zero nos demais resultados.
     */
    public function devido(Apostas $aposta): string
    {
        return $aposta->resultado === ResultadoAposta::Vencedor ? $aposta->total_a_pagar() : '0.00';
    }

    /**
     * Valor devido diferente do pago numa aposta que recebe no saldo.
     */
    public function pendente(Apostas $aposta): bool
    {
        return $this->elegivel($aposta) && bccomp($this->devido($aposta), (string) $aposta->valor_pago, 2) !== 0;
    }

    public function minutos_janela(): int
    {
        return $this->minutos_janela ??= (int) Configuracoes::atual()->minutos_pagamento_premio;
    }

    private function janela_vencida(Apostas $aposta): bool
    {
        return $aposta->apurada_em === null || $aposta->apurada_em->copy()->addMinutes($this->minutos_janela())->lte(now());
    }

    private function creditar(Apostas $aposta, OrigemApuracao $origem, int $centavos, int $pago): void
    {
        // sem palpite ativo a aposta só devolve o valor apostado (todos os jogos cancelados)
        $devolucao = $pago === 0 && ! $aposta->palpites_ativos()->exists();

        $transacao = $this->saldo->creditar(
            $this->cliente($aposta),
            $this->carteira($aposta),
            SaldoClientes::para_decimal($centavos),
            $devolucao ? OrigemTransacao::DevoluçãoAposta : OrigemTransacao::Prêmio,
            null,
            $aposta->id,
            "Aposta {$aposta->codigo}",
        );

        $tipo = match (true) {
            $pago > 0 => TipoOperacaoApuracao::PrêmioAjustado,
            $devolucao => TipoOperacaoApuracao::ValorDevolvido,
            default => TipoOperacaoApuracao::PrêmioPago,
        };

        $motivo = $devolucao ? 'Todos os palpites da aposta foram cancelados' : 'Aposta vencedora';
        $this->registrar($aposta, $origem, $tipo, $transacao, $pago, $pago + $centavos, $motivo);
    }

    private function estornar(Apostas $aposta, OrigemApuracao $origem, int $centavos, int $devido): void
    {
        $transacao = $this->saldo->debitar(
            $this->cliente($aposta),
            $this->carteira($aposta),
            SaldoClientes::para_decimal($centavos),
            OrigemTransacao::Estorno,
            null,
            $aposta->id,
            "Aposta {$aposta->codigo}",
            permitir_negativo: true,
        );

        $tipo = $devido === 0 ? TipoOperacaoApuracao::PrêmioEstornado : TipoOperacaoApuracao::PrêmioAjustado;
        $this->registrar($aposta, $origem, $tipo, $transacao, $devido + $centavos, $devido, 'Resultado corrigido depois do pagamento');
    }

    private function registrar(Apostas $aposta, OrigemApuracao $origem, TipoOperacaoApuracao $tipo, ClientesTransacoes $transacao, int $pago_antes, int $pago_depois, string $motivo): void
    {
        $this->registro->registrar(
            $tipo,
            $origem,
            ['apostas_id' => $aposta->id, 'clientes_id' => $aposta->clientes_id],
            ['valor_pago' => SaldoClientes::para_decimal($pago_antes), 'saldo' => (string) $transacao->saldo_anterior],
            ['valor_pago' => SaldoClientes::para_decimal($pago_depois), 'saldo' => (string) $transacao->saldo_posterior],
            $motivo,
            (string) $transacao->valor,
            $transacao->carteira,
            $transacao->id,
        );
    }

    private function marcar_pendente(Apostas $aposta, bool $pendente): void
    {
        if ($aposta->liquidacao_pendente !== $pendente) {
            $aposta->forceFill(['liquidacao_pendente' => $pendente])->save();
        }
    }

    private function carteira(Apostas $aposta): Carteira
    {
        return $aposta->forma_pagamento === FormaPagamento::Saldo ? Carteira::Saldo : Carteira::PromoçãoEsportes;
    }

    private function cliente(Apostas $aposta): Clientes
    {
        return Clientes::withTrashed()->findOrFail($aposta->clientes_id);
    }
}
