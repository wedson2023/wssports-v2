<?php

namespace App\Services;

use App\Enums\ModalidadeEncerramento;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Clientes;
use App\Support\Apostador;
use App\Support\CodigosCotacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cotações atuais dos palpites em aberto e oferta de encerramento da aposta (spec 010, R-04). As
 * cotações saem do mesmo CriacaoApostas::montar da criação da aposta, com o público de quem vê.
 */
class CalculoEncerramento
{
    private const ESCALA = 10;

    public function __construct(
        private CriacaoApostas $criacao,
        private ElegibilidadeEncerramento $elegibilidade,
        private ConfiguracoesEncerramento $configuracoes,
    ) {}

    /**
     * Palpites da aposta com o que o cálculo e o bilhete usam.
     *
     * @return Collection<int, ApostasPalpites>
     */
    public static function palpites(Apostas $aposta): Collection
    {
        return $aposta->palpites()
            ->with(['confronto.ao_vivo', 'confronto.resultado', 'campeonato', 'jogador', 'especial', 'opcao_especial'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Oferta de encerramento da aposta agora (R-04): prêmio da parte que vale ÷ cotações atuais dos
     * palpites em aberto, menos a margem, truncada e nunca maior que o prêmio; abaixo do mínimo
     * não há oferta. Traz a fotografia dos palpites em aberto (placar e cotação) usada no token e
     * no registro.
     *
     * @param  Collection<int, ApostasPalpites>|null  $palpites  já carregados (CalculoEncerramento::palpites)
     * @return array{disponivel: bool, motivo: ?string, oferta: ?string, minimo: string, margem: string, ao_vivo: bool, fotografia: list<array<string, mixed>>}
     */
    public function oferta(Apostas $aposta, ?Collection $palpites = null): array
    {
        $palpites ??= self::palpites($aposta);
        $cliente = Clientes::withTrashed()->find($aposta->clientes_id);
        $cotacoes = $cliente !== null ? $this->cotacoes_atuais(Apostador::cliente($cliente), $palpites) : [];
        $ao_vivo = collect($cotacoes)->contains(fn (array $situacao) => $situacao['iniciado']);
        $margem = $this->configuracoes->margem($ao_vivo);
        $minimo = $this->minimo($aposta);
        $avaliacao = $this->elegibilidade->avaliar($aposta, $palpites, $cotacoes);

        $resposta = [
            'disponivel' => false,
            'motivo' => $avaliacao['motivo'],
            'oferta' => null,
            'minimo' => $minimo,
            'margem' => $margem,
            'ao_vivo' => $ao_vivo,
            'fotografia' => $this->fotografia($palpites, $cotacoes),
        ];

        if (! $avaliacao['elegivel']) {
            return $resposta;
        }

        $oferta = $this->calcular((string) $aposta->premio, (string) $aposta->fracao_restante, array_column($cotacoes, 'cotacao_atual'), $margem);

        if (bccomp($oferta, $minimo, 2) < 0) {
            return [...$resposta, 'motivo' => 'Oferta abaixo do mínimo para encerrar.'];
        }

        return [...$resposta, 'disponivel' => true, 'oferta' => $oferta];
    }

    /**
     * Fórmula da oferta (R-04): truncar(P ÷ C × (100 − margem) ÷ 100), limitada a P, com P = prêmio ×
     * fração restante e C = produto das cotações atuais dos palpites em aberto.
     *
     * @param  list<string>  $cotacoes_atuais
     */
    public function calcular(string $premio, string $fracao, array $cotacoes_atuais, string $margem): string
    {
        $premio_em_jogo = bcmul($premio, $fracao, self::ESCALA);
        $produto = array_reduce($cotacoes_atuais, fn (string $acumulado, string $cotacao) => bcmul($acumulado, $cotacao, self::ESCALA), '1');
        $justo = bcdiv($premio_em_jogo, $produto, self::ESCALA);
        $oferta = bcdiv(bcmul($justo, bcsub('100', $margem, 2), self::ESCALA), '100', 2);
        $teto = bcadd($premio_em_jogo, '0', 2);

        return bccomp($oferta, $teto, 2) > 0 ? $teto : $oferta;
    }

    /**
     * Menor oferta aceita: o maior entre o valor mínimo e o percentual do valor que continua
     * valendo (FR-022); também é o menor encerramento parcial.
     */
    public function minimo(Apostas $aposta): string
    {
        $percentual = bcdiv(bcmul($aposta->valor_em_jogo(), $this->configuracoes->percentual_minimo(), self::ESCALA), '100', 2);
        $valor = bcadd($this->configuracoes->valor_minimo(), '0', 2);

        return bccomp($percentual, $valor, 2) > 0 ? $percentual : $valor;
    }

    /**
     * Encerramento parcial disponível: ligado e abaixo do limite de parciais da aposta (FR-029).
     *
     * @return array{disponivel: bool, motivo: ?string, restantes: int}
     */
    public function parcial(Apostas $aposta): array
    {
        if (! $this->configuracoes->parcial_habilitado()) {
            return ['disponivel' => false, 'motivo' => 'Encerramento parcial indisponível no momento.', 'restantes' => 0];
        }

        $feitos = $aposta->encerramentos()->queValem()->where('modalidade', ModalidadeEncerramento::Parcial)->count();
        $restantes = max(0, $this->configuracoes->limite_parciais() - $feitos);

        return [
            'disponivel' => $restantes > 0,
            'motivo' => $restantes > 0 ? null : 'Limite de encerramentos parciais atingido.',
            'restantes' => $restantes,
        ];
    }

    /**
     * Placar, período e cotação de cada palpite em aberto no momento (registro e token).
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @param  array<int, array<string, mixed>>  $cotacoes
     * @return list<array<string, mixed>>
     */
    private function fotografia(Collection $palpites, array $cotacoes): array
    {
        $fotografia = [];

        foreach ($cotacoes as $id => $situacao) {
            $palpite = $palpites->firstWhere('id', $id);
            $jogo = $situacao['jogo_ao_vivo'];

            $fotografia[] = [
                'id' => $id,
                'jogo' => $situacao['nome_jogo'],
                // cotação aceita na aposta (a "cotação original" da spec); não é a coluna cotacao_original
                'cotacao_aposta' => (string) $palpite?->cotacao_final,
                'cotacao_atual' => $situacao['cotacao_atual'],
                'placar_casa' => $jogo !== null ? (int) $jogo->placar_casa : null,
                'placar_fora' => $jogo !== null ? (int) $jogo->placar_fora : null,
                'periodo' => $jogo?->situacao,
                'minuto' => $jogo !== null ? (int) $jogo->minuto : null,
                'ultima_atualizacao_em' => $jogo?->ultima_atualizacao_em !== null ? Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->toIso8601String() : null,
                'jogo_com_resultado' => $situacao['com_resultado'],
                'travado' => $situacao['travado'],
            ];
        }

        return $fotografia;
    }

    /**
     * Palpite em aberto: ativo e ainda sem resultado.
     */
    public static function em_aberto(ApostasPalpites $palpite): bool
    {
        return $palpite->situacao === SituacaoPalpite::Ativo && $palpite->resultado === ResultadoAposta::Aguardando;
    }

    /**
     * Cotação atual de cada palpite em aberto (tabela da R-04): pré-jogo antes do início, ao vivo
     * depois; sem cotação para jogador depois do início, especial e jogo iniciado sem ao vivo.
     * Palpites decididos ficam de fora. Espera os palpites com confronto.ao_vivo e
     * confronto.resultado carregados.
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @return array<int, array{cotacao_atual: ?string, iniciado: bool, ao_vivo: bool, travado: bool, sem_cobertura: bool, com_resultado: bool, jogo_ao_vivo: ?object, nome_jogo: string}>
     */
    public function cotacoes_atuais(Apostador $apostador, Collection $palpites): array
    {
        $situacoes = [];
        $pedidos = [];

        foreach ($palpites->filter(fn (ApostasPalpites $palpite) => self::em_aberto($palpite)) as $palpite) {
            $confronto = $palpite->confronto;
            $jogo_ao_vivo = $confronto?->ao_vivo;
            $iniciado = $palpite->confrontos_ao_vivo_id !== null || $jogo_ao_vivo !== null || ($confronto?->data_inicio?->isPast() ?? false);

            $situacoes[$palpite->id] = [
                'cotacao_atual' => null,
                'iniciado' => $iniciado,
                'ao_vivo' => $iniciado && $jogo_ao_vivo !== null,
                'travado' => false,
                'sem_cobertura' => $iniciado && $jogo_ao_vivo === null && ! $palpite->e_especial(),
                'com_resultado' => (bool) $confronto?->resultado?->apuravel(),
                'jogo_ao_vivo' => null,
                'nome_jogo' => $this->nome_jogo($palpite),
            ];

            $pedido = $this->pedido($palpite, $iniciado, $jogo_ao_vivo?->id);

            if ($pedido !== null) {
                $pedidos[$palpite->id] = $pedido;
            }
        }

        if ($pedidos === []) {
            return $situacoes;
        }

        $montados = $this->criacao->montar($apostador, array_values($pedidos));

        foreach (array_keys($pedidos) as $indice => $id) {
            $montado = $montados[$indice];
            $cotacao = $montado['motivo'] === null && bccomp($montado['cotacao_atual'], '0', 2) > 0 ? $montado['cotacao_atual'] : null;

            $situacoes[$id]['cotacao_atual'] = $cotacao;
            $situacoes[$id]['travado'] = (bool) $montado['travado'];
            $situacoes[$id]['jogo_ao_vivo'] = $montado['ao_vivo'];
        }

        return $situacoes;
    }

    /**
     * Pedido no formato do CriacaoApostas::montar, ou nulo quando o palpite não tem cotação atual.
     *
     * @return array<string, mixed>|null
     */
    private function pedido(ApostasPalpites $palpite, bool $iniciado, ?int $confrontos_ao_vivo_id): ?array
    {
        if ($palpite->e_especial()) {
            return null;
        }

        $jogador = $palpite->codigo_cotacao === CodigosCotacao::JOGADOR;
        $base = [
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'confrontos_jogadores_id' => $palpite->confrontos_jogadores_id,
            'cotacao_vista' => (string) $palpite->cotacao_final,
        ];

        if (! $iniciado) {
            // jogo do pré-jogo só tem cotação válida enquanto Aguardando (fora de suspensão ou cancelamento)
            if ($palpite->confronto?->situacao !== SituacaoConfronto::Aguardando) {
                return null;
            }

            return [...$base, 'confrontos_id' => $palpite->confrontos_id];
        }

        // o ao vivo não tem cotação de jogador; jogo iniciado sem ao vivo fica sem cobertura
        if ($jogador || $confrontos_ao_vivo_id === null) {
            return null;
        }

        return [...$base, 'confrontos_ao_vivo_id' => $confrontos_ao_vivo_id];
    }

    private function nome_jogo(ApostasPalpites $palpite): string
    {
        if ($palpite->e_especial()) {
            return (string) $palpite->especial?->nome;
        }

        $confronto = $palpite->confronto;

        return $confronto !== null ? "{$confronto->time_casa} x {$confronto->time_fora}" : 'informado';
    }
}
