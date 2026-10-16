<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\EfeitoAcaoCassino;
use App\Enums\OrigemTransacao;
use App\Enums\ResultadoChamadaCassino;
use App\Exceptions\RecusaCarteiraCassinoException;
use App\Models\CassinosAcoes;
use App\Models\CassinosJogosCanais;
use App\Models\CassinosRodadas;
use App\Models\CassinosSessoes;
use App\Models\Clientes;
use Illuminate\Support\Facades\DB;

/**
 * Carteira do cliente respondendo ao provedor do cassino (spec 011, FR-018 a FR-025, R-14, R-16,
 * R-17): consulta de saldo (getBalance) e lançamento de rodada (writeBet). Cada rodada move o saldo
 * uma única vez (prêmio − aposta) pelo SaldoClientes, com a linha do cliente travada; o mesmo
 * tradeId repetido devolve a mesma resposta sem mover nada.
 */
class CarteiraCassino
{
    public function __construct(
        private SaldoClientes $saldo,
        private EscolhaCarteiraCassino $escolha,
        private RolloverClientes $rollover,
        private CancelamentoRodadasCassino $cancelamento,
    ) {}

    /**
     * getBalance: saldo da carteira da sessão mais recente do cliente.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: array<string, mixed>, 1: ResultadoChamadaCassino}
     */
    public function saldo(array $dados): array
    {
        $cliente = $this->cliente($dados['login'] ?? null);

        if ($cliente === null) {
            return [$this->falha('user_not_found'), ResultadoChamadaCassino::Recusada];
        }

        // o provedor manda o sessionId também na consulta de saldo: com ele, a carteira é a da sessão
        // exata (dois jogos abertos não se confundem); sem ele, a da sessão mais recente
        $sessao_provedor = $this->texto($dados['sessionId'] ?? null);
        $sessao = ($sessao_provedor !== null
            ? CassinosSessoes::where('sessao_provedor', $sessao_provedor)->where('clientes_id', $cliente->id)->first()
            : null) ?? $this->sessao_recente($cliente);

        return [$this->sucesso($cliente, $this->escolha->carteira_saldo($cliente, $sessao)), ResultadoChamadaCassino::Aceita];
    }

    /**
     * Identificação da rodada no jogo: a PG Soft manda em betInfo.round_id (objeto); outros
     * provedores, em round ou betInfo como texto (a Amatic manda betInfo como JSON em texto). Liga
     * a aposta ao prêmio da mesma rodada.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function rodada_provedor(array $dados): ?string
    {
        $info = $dados['betInfo'] ?? null;

        if (is_string($info) && str_starts_with(ltrim($info), '{')) {
            $info = json_decode($info, true) ?? $info;
        }
        $valor = $dados['round'] ?? (is_array($info) ? ($info['round_id'] ?? $info['roundId'] ?? null) : $info) ?? $dados['roundId'] ?? null;

        return is_scalar($valor) && trim((string) $valor) !== '' ? mb_substr(trim((string) $valor), 0, 100) : null;
    }

    /**
     * writeBet: lança a rodada (aposta, prêmio, rodada grátis, cancelamento ou reembolso).
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: array<string, mixed>, 1: ResultadoChamadaCassino}
     */
    public function lancar(array $dados): array
    {
        $cliente = $this->cliente($dados['login'] ?? null);

        if ($cliente === null) {
            return [$this->falha('user_not_found'), ResultadoChamadaCassino::Recusada];
        }

        $trade_id = $this->texto($dados['tradeId'] ?? null);
        $aposta = $this->centavos($dados['bet'] ?? 0);
        $premio = $this->centavos($dados['win'] ?? 0);

        if ($trade_id === null || $aposta === null || $premio === null) {
            return [$this->falha('wrong_params', $cliente), ResultadoChamadaCassino::Recusada];
        }

        try {
            return DB::transaction(function () use ($cliente, $dados, $trade_id, $aposta, $premio) {
                // a trava da linha do cliente serializa as chamadas simultâneas (ex.: Aviator com
                // duas apostas), no lugar do sleep e do cache do sistema antigo
                $bloqueado = Clientes::withTrashed()->lockForUpdate()->findOrFail($cliente->id);

                $repetida = CassinosRodadas::where('trade_id', $trade_id)->first();

                if ($repetida !== null) {
                    return [$this->sucesso_rodada($bloqueado, $repetida), ResultadoChamadaCassino::Repetida];
                }

                [$acao, $efeito] = $this->efeito($dados['action'] ?? null);
                $sessao = $this->sessao($bloqueado, $dados);

                // no cancelamento, o valor devolvido vem em bet ou, no Spribe, em win (bet 0)
                $rodada = $efeito->devolve_aposta()
                    ? $this->cancelamento->devolver($bloqueado, $sessao, $acao, $efeito, $aposta > 0 ? $aposta : $premio, $trade_id, $dados)
                    : $this->jogar($bloqueado, $sessao, $acao, $efeito, $aposta, $premio, $trade_id, $dados);

                $sessao->forceFill([
                    'quantidade_rodadas' => $sessao->quantidade_rodadas + 1,
                    'ultima_rodada_em' => now(),
                ])->save();

                return [$this->sucesso_rodada($bloqueado, $rodada), ResultadoChamadaCassino::Aceita];
            });
        } catch (RecusaCarteiraCassinoException $recusa) {
            return [$this->falha($recusa->codigo, $cliente->fresh()), ResultadoChamadaCassino::Recusada];
        }
    }

    /**
     * Aposta, prêmio, aposta com prêmio ou rodada grátis: um único movimento líquido na carteira da
     * aposta (FR-021, FR-024).
     *
     * @param  array<string, mixed>  $dados
     */
    private function jogar(Clientes $cliente, CassinosSessoes $sessao, ?CassinosAcoes $acao, EfeitoAcaoCassino $efeito, int $aposta, int $premio, string $trade_id, array $dados): CassinosRodadas
    {
        if ($efeito === EfeitoAcaoCassino::RodadaGrátis) {
            $aposta = 0;
        }

        if ($efeito === EfeitoAcaoCassino::Prêmio && $aposta > 0) {
            throw new RecusaCarteiraCassinoException('wrong_params');
        }

        $rodada_provedor = self::rodada_provedor($dados);

        if ($aposta > 0) {
            // cliente desativado não faz apostas novas; prêmios e devoluções continuam
            if (! $cliente->pode_acessar()) {
                throw new RecusaCarteiraCassinoException('fail_balance');
            }

            $carteira = $this->escolha->escolher($cliente, $sessao, SaldoClientes::para_decimal($aposta))
                ?? throw new RecusaCarteiraCassinoException('fail_balance');
            $sessao->carteira = $carteira;
        } else {
            // prêmio separado: volta à carteira da aposta da mesma rodada (round_id); sem ela, à da
            // última aposta da sessão
            $apostas = fn () => CassinosRodadas::where('cassinos_sessoes_id', $sessao->id)
                ->whereIn('efeito', [EfeitoAcaoCassino::Aposta, EfeitoAcaoCassino::ApostaComPrêmio])
                ->where('valor_aposta', '>', 0)
                ->orderByDesc('id');

            $carteira = ($rodada_provedor !== null ? $apostas()->where('rodada_provedor', $rodada_provedor)->first()?->carteira : null)
                ?? $apostas()->first()?->carteira
                ?? $sessao->carteira
                ?? Carteira::Saldo;
        }

        $anterior = (string) $cliente->getRawOriginal($carteira->coluna());
        $liquido = $premio - $aposta;

        $transacao = match (true) {
            $liquido > 0 => $this->saldo->creditar($cliente, $carteira, SaldoClientes::para_decimal($liquido), OrigemTransacao::Cassino, null, $sessao->id),
            $liquido < 0 => $this->saldo->debitar($cliente, $carteira, SaldoClientes::para_decimal(-$liquido), OrigemTransacao::Cassino, null, $sessao->id),
            default => null,
        };

        $rodada = CassinosRodadas::create([
            'cassinos_sessoes_id' => $sessao->id,
            'clientes_id' => $cliente->id,
            'cassinos_acoes_id' => $acao?->id,
            'acao' => $acao?->acao,
            'efeito' => $efeito,
            'trade_id' => $trade_id,
            'rodada_provedor' => $rodada_provedor,
            'valor_aposta' => SaldoClientes::para_decimal($aposta),
            'valor_premio' => SaldoClientes::para_decimal($premio),
            'carteira' => $carteira,
            'saldo_anterior' => $anterior,
            'saldo_posterior' => (string) $cliente->getRawOriginal($carteira->coluna()),
            'clientes_transacoes_id' => $transacao?->id,
            // campos do provedor para conferência (round_finished e winLose vêm da PG Soft)
            'dados_provedor' => array_intersect_key($dados, array_flip(['matrix', 'WinLines', 'betInfo', 'date', 'round', 'round_finished', 'winLose'])) ?: null,
        ]);

        if ($aposta > 0) {
            $this->rollover->somar_cassino($rodada);
        }

        $sessao->total_apostado = bcadd((string) $sessao->total_apostado, SaldoClientes::para_decimal($aposta), 2);
        $sessao->total_ganho = bcadd((string) $sessao->total_ganho, SaldoClientes::para_decimal($premio), 2);

        return $rodada;
    }

    /**
     * Tipo de ação pela tabela de ações; ação vazia vale como aposta com prêmio (padrão do PDF).
     *
     * @return array{0: ?CassinosAcoes, 1: EfeitoAcaoCassino}
     *
     * @throws RecusaCarteiraCassinoException ação desconhecida ou desativada
     */
    private function efeito(mixed $acao_recebida): array
    {
        $acao = mb_strtoupper((string) $this->texto($acao_recebida));

        if ($acao === '') {
            return [null, EfeitoAcaoCassino::ApostaComPrêmio];
        }

        $registro = CassinosAcoes::where('acao', $acao)->where('ativo', true)->first()
            ?? throw new RecusaCarteiraCassinoException('unknown_action');

        return [$registro, $registro->efeito];
    }

    /**
     * Sessão do lançamento (R-11): pelo sessionId; sem ele, a sessão recente do mesmo cliente e jogo
     * aberta pelo site e ainda sem rodadas (o provedor pode usar nas rodadas um sessionId diferente do
     * devolvido na abertura), que passa a usar o sessionId recebido; sem nenhuma, uma sessão nova.
     *
     * @param  array<string, mixed>  $dados
     */
    private function sessao(Clientes $cliente, array $dados): CassinosSessoes
    {
        $sessao_provedor = $this->texto($dados['sessionId'] ?? null);
        $codigo = (string) ($this->texto($dados['gameId'] ?? null) ?? '');

        if ($sessao_provedor !== null) {
            $sessao = CassinosSessoes::where('sessao_provedor', $sessao_provedor)->where('clientes_id', $cliente->id)->first();

            if ($sessao !== null) {
                return $sessao;
            }
        }

        $recente = CassinosSessoes::where('clientes_id', $cliente->id)
            ->where('codigo_provedor', $codigo)
            // com sessionId, só uma sessão que ainda não tem rodadas de outra sessão do provedor;
            // sem sessionId, a mais recente do jogo
            ->when($sessao_provedor !== null, fn ($consulta) => $consulta->where(fn ($c) => $c->where('quantidade_rodadas', 0)->orWhereNull('sessao_provedor')))
            ->whereRaw('coalesce(ultima_rodada_em, aberta_em) >= ?', [now()->subHours((int) config('cassino.horas_sessao_saldo'))])
            ->orderByDesc('id')
            ->first();

        // o sessionId é único: se já for de outra sessão (de outro cliente), não é reaproveitado
        $sessao_provedor_livre = $sessao_provedor !== null && ! CassinosSessoes::where('sessao_provedor', $sessao_provedor)->exists()
            ? $sessao_provedor
            : null;

        if ($recente !== null) {
            $recente->sessao_provedor = $sessao_provedor_livre ?? $recente->sessao_provedor;

            return $recente;
        }

        $canal = CassinosJogosCanais::where('codigo_provedor', $codigo)->orderByDesc('id')->first();

        return CassinosSessoes::create([
            'clientes_id' => $cliente->id,
            'cassinos_jogos_id' => $canal?->cassinos_jogos_id,
            'cassinos_jogos_canais_id' => $canal?->id,
            'codigo_provedor' => mb_substr($codigo, 0, 30),
            'sessao_provedor' => $sessao_provedor_livre,
            'bonus_primeiro' => (bool) $canal?->bonus_primeiro,
            'aberta_em' => now(),
        ]);
    }

    private function sessao_recente(Clientes $cliente): ?CassinosSessoes
    {
        return CassinosSessoes::where('clientes_id', $cliente->id)
            ->whereRaw('coalesce(ultima_rodada_em, aberta_em) >= ?', [now()->subHours((int) config('cassino.horas_sessao_saldo'))])
            ->orderByRaw('coalesce(ultima_rodada_em, aberta_em) desc')
            ->first();
    }

    /**
     * Cliente do login enviado ao provedor (prefixo + id, R-12); excluído é tratado como inexistente.
     */
    private function cliente(mixed $login): ?Clientes
    {
        $prefixo = (string) config('services.provedor_cassino.prefixo_login');
        $login = (string) $this->texto($login);

        if (! str_starts_with($login, $prefixo) || ! ctype_digit($id = substr($login, strlen($prefixo)))) {
            return null;
        }

        return Clientes::find((int) $id);
    }

    /**
     * Valor do provedor em centavos; null quando não é um número maior ou igual a zero.
     */
    private function centavos(mixed $valor): ?int
    {
        if (! is_numeric($valor) || (float) $valor < 0) {
            return null;
        }

        return SaldoClientes::para_centavos(is_float($valor) ? number_format($valor, 2, '.', '') : trim((string) $valor));
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) && trim((string) $valor) !== '' ? mb_substr(trim((string) $valor), 0, 100) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sucesso(Clientes $cliente, Carteira $carteira): array
    {
        return [
            'status' => 'success',
            'error' => '',
            'login' => $this->login($cliente),
            'balance' => SaldoClientes::para_decimal(SaldoClientes::para_centavos((string) $cliente->getRawOriginal($carteira->coluna()))),
            'currency' => 'BRL',
        ];
    }

    /**
     * Resposta do lançamento, a mesma na repetição: saldo posterior gravado e o id da rodada.
     *
     * @return array<string, mixed>
     */
    private function sucesso_rodada(Clientes $cliente, CassinosRodadas $rodada): array
    {
        return [
            'status' => 'success',
            'error' => '',
            'login' => $this->login($cliente),
            'balance' => (string) $rodada->saldo_posterior,
            'currency' => 'BRL',
            'operationId' => $rodada->id,
        ];
    }

    /**
     * Falha no formato do provedor; com o cliente conhecido, leva também o saldo atual.
     *
     * @return array<string, mixed>
     */
    public function falha(string $codigo, ?Clientes $cliente = null): array
    {
        $resposta = ['status' => 'fail', 'error' => $codigo];

        if ($cliente === null) {
            return $resposta;
        }

        $carteira = $this->escolha->carteira_saldo($cliente, $this->sessao_recente($cliente));

        return [...$resposta, ...array_diff_key($this->sucesso($cliente, $carteira), ['status' => 0, 'error' => 0])];
    }

    private function login(Clientes $cliente): string
    {
        return config('services.provedor_cassino.prefixo_login').$cliente->id;
    }
}
