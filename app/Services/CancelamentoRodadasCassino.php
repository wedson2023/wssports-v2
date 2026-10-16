<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\EfeitoAcaoCassino;
use App\Enums\OrigemTransacao;
use App\Models\CassinosAcoes;
use App\Models\CassinosRodadas;
use App\Models\CassinosSessoes;
use App\Models\Clientes;

/**
 * Cancelamento e reembolso de aposta pelo provedor do cassino (spec 011, FR-023, R-19): a aposta
 * volta à carteira de onde saiu e o rollover que ela somou é desfeito. O reembolso nunca confere
 * saldo. Roda dentro da transação do lançamento, com o cliente já bloqueado.
 */
class CancelamentoRodadasCassino
{
    /**
     * Campos em que o provedor pode informar o lançamento original (a conferir no registro de
     * chamadas).
     */
    private const CAMPOS_ORIGINAL = ['refundTradeId', 'betTradeId', 'originalTradeId'];

    public function __construct(private SaldoClientes $saldo, private RolloverClientes $rollover) {}

    /**
     * @param  int  $valor_centavos  valor devolvido informado pelo provedor (bet; o Spribe manda em win)
     * @param  array<string, mixed>  $dados
     */
    public function devolver(
        Clientes $cliente,
        CassinosSessoes $sessao,
        ?CassinosAcoes $acao,
        EfeitoAcaoCassino $efeito,
        int $valor_centavos,
        string $trade_id,
        array $dados,
    ): CassinosRodadas {
        $original = $this->original($cliente, $sessao, $valor_centavos, $dados);

        // com a aposta original, devolve no máximo o que ela apostou; o cancelamento sem original
        // não move saldo (fica registrado para conferência)
        $devolvido = match (true) {
            $original !== null => min($valor_centavos, SaldoClientes::para_centavos((string) $original->valor_aposta)),
            $efeito === EfeitoAcaoCassino::Reembolso => $valor_centavos,
            default => 0,
        };
        $carteira = $original?->carteira ?? $sessao->carteira ?? Carteira::Saldo;

        $anterior = (string) $cliente->getRawOriginal($carteira->coluna());
        $transacao = $devolvido > 0
            ? $this->saldo->creditar($cliente, $carteira, SaldoClientes::para_decimal($devolvido), OrigemTransacao::Cassino, null, $sessao->id)
            : null;

        $rodada = CassinosRodadas::create([
            'cassinos_sessoes_id' => $sessao->id,
            'clientes_id' => $cliente->id,
            'cassinos_acoes_id' => $acao?->id,
            'acao' => $acao?->acao,
            'efeito' => $efeito,
            'trade_id' => $trade_id,
            'rodada_provedor' => CarteiraCassino::rodada_provedor($dados),
            'valor_aposta' => SaldoClientes::para_decimal($devolvido),
            'valor_premio' => '0.00',
            'carteira' => $carteira,
            'saldo_anterior' => $anterior,
            'saldo_posterior' => (string) $cliente->getRawOriginal($carteira->coluna()),
            'clientes_transacoes_id' => $transacao?->id,
            'cassinos_rodadas_id' => $original?->id,
            'dados_provedor' => $dados ?: null,
        ]);

        if ($original !== null) {
            $this->rollover->desfazer_cassino($original);
            $original->forceFill(['cancelada_em' => now()])->save();
        }

        $total = bcsub((string) $sessao->total_apostado, SaldoClientes::para_decimal($devolvido), 2);
        $sessao->total_apostado = bccomp($total, '0', 2) < 0 ? '0.00' : $total;

        return $rodada;
    }

    /**
     * Aposta original, nesta ordem: pelo tradeId informado num dos campos; pelo próprio tradeId do
     * cancelamento sem o prefixo (o Spribe manda "cancel" + tradeId da aposta); pela aposta da mesma
     * rodada (betInfo.round_id); pela última aposta não cancelada da sessão com o mesmo valor.
     *
     * @param  array<string, mixed>  $dados
     */
    private function original(Clientes $cliente, CassinosSessoes $sessao, int $valor_centavos, array $dados): ?CassinosRodadas
    {
        $apostas = fn () => CassinosRodadas::where('clientes_id', $cliente->id)
            ->whereIn('efeito', [EfeitoAcaoCassino::Aposta, EfeitoAcaoCassino::ApostaComPrêmio])
            ->whereNull('cancelada_em')
            ->where('valor_aposta', '>', 0);

        foreach (self::CAMPOS_ORIGINAL as $campo) {
            $trade_id = $this->texto($dados[$campo] ?? null);

            if ($trade_id !== null) {
                return $apostas()->where('trade_id', $trade_id)->first();
            }
        }

        $proprio = (string) $this->texto($dados['tradeId'] ?? null);
        $sem_prefixo = preg_replace('/^(cancel|refund|rollback)[_\-:]?/i', '', $proprio);

        if ($sem_prefixo !== '' && $sem_prefixo !== $proprio) {
            $pelo_trade = $apostas()->where('trade_id', $sem_prefixo)->first();

            if ($pelo_trade !== null) {
                return $pelo_trade;
            }
        }

        $rodada_provedor = CarteiraCassino::rodada_provedor($dados);

        if ($rodada_provedor !== null) {
            $pela_rodada = $apostas()->where('cassinos_sessoes_id', $sessao->id)->where('rodada_provedor', $rodada_provedor)->orderByDesc('id')->first();

            if ($pela_rodada !== null) {
                return $pela_rodada;
            }
        }

        return $apostas()
            ->where('cassinos_sessoes_id', $sessao->id)
            ->where('valor_aposta', SaldoClientes::para_decimal($valor_centavos))
            ->orderByDesc('id')
            ->first();
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) && trim((string) $valor) !== '' ? mb_substr(trim((string) $valor), 0, 100) : null;
    }
}
