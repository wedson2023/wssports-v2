<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoBloco;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasBlocos;
use App\Models\ApostasEncerramentos;
use App\Models\ApostasPalpites;
use App\Models\Clientes;
use App\Support\Apostador;
use App\Support\CodigosCotacao;
use App\Support\NomesCotacoes;
use App\Support\PlacarConfronto;
use App\Support\ResultadosCotacao;
use Carbon\CarbonTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados do bilhete em tempo real (spec 010, contracts/api.md seção 1): resumo, palpites com placar,
 * selos "ganhando/perdendo agora" e cotações atuais de quem vê, progresso e canais do tempo real.
 * O bloco de encerramento só existe para o dono.
 */
class BilheteTempoReal
{
    private const FUTEBOL = 'FUTEBOL';

    public const SELO_GANHANDO = 'Ganhando agora';

    public const SELO_PERDENDO = 'Perdendo agora';

    public const SELO_AGUARDANDO = 'Aguardando';

    public function __construct(
        private CalculoEncerramento $calculo,
        private ResultadosCotacao $regras,
        private EncerramentoApostas $encerramento,
        private ConfiguracoesEncerramento $configuracoes,
    ) {}

    /**
     * Situações em que o bilhete não existe para o público (FR-011).
     *
     * @return list<SituacaoAposta>
     */
    public static function situacoes_ocultas(): array
    {
        return [SituacaoAposta::Pendente, SituacaoAposta::EmAnálise, SituacaoAposta::Recusada, SituacaoAposta::Expirada];
    }

    /**
     * @return array<string, mixed>
     */
    public function montar(Apostas $aposta, ?Clientes $cliente_logado, ?string $oferta_anterior, CarbonTimeZone $fuso): array
    {
        $dono = $cliente_logado !== null && $aposta->clientes_id === $cliente_logado->id;
        $palpites = CalculoEncerramento::palpites($aposta);
        $blocos = $aposta->blocos()->orderBy('id')->get();

        $apostador = $dono ? Apostador::cliente($cliente_logado) : Apostador::visitante();
        $cotacoes = $this->calculo->cotacoes_atuais($apostador, $palpites);

        $itens = $palpites->map(fn (ApostasPalpites $palpite) => $this->palpite($palpite, $cotacoes[$palpite->id] ?? null, $fuso))->all();
        $final = $this->final($aposta);

        return [
            'codigo' => $aposta->codigo,
            'situacao' => $aposta->situacao->value,
            'resultado' => $aposta->resultado->value,
            'final' => $final,
            'cliente' => $dono ? $aposta->nome : self::nome_abreviado($aposta->nome),
            'dono' => $dono,
            'criada_em' => $this->data($aposta->recebida_em, $fuso),
            'valor' => (string) $aposta->valor,
            'valor_em_jogo' => $aposta->valor_em_jogo(),
            'cotacao_total' => (string) $aposta->cotacao_total,
            'total_a_pagar' => $aposta->total_a_pagar(),
            'valor_acrescido' => (string) $aposta->valor_acrescido,
            'progresso' => $this->progresso($itens, $blocos),
            'atualizado_em' => $this->data(now(), $fuso),
            'canais' => $final ? ['aposta' => null, 'confrontos' => []] : $this->canais($aposta, $palpites),
            'blocos' => $blocos->map(fn (ApostasBlocos $bloco) => [
                'id' => $bloco->id,
                'confrontos_id' => $bloco->confrontos_id,
                'cotacao' => (string) $bloco->cotacao,
                'situacao' => $bloco->situacao->value,
            ])->all(),
            'palpites' => $itens,
            'encerramento' => match (true) {
                ! $dono => null,
                $final => $this->encerramento_final($aposta, $fuso),
                default => $this->encerramento($aposta, $palpites, $oferta_anterior, $fuso),
            },
        ];
    }

    /**
     * Aposta no estado final: só os encerramentos registrados (valor e crédito), para o dono ver o
     * que recebeu; nulo quando ela não teve encerramento.
     *
     * @return array<string, mixed>|null
     */
    private function encerramento_final(Apostas $aposta, CarbonTimeZone $fuso): ?array
    {
        $registrados = $this->registrados($aposta, $fuso);

        return $registrados === [] ? null : ['disponivel' => false, 'motivo' => null, 'final' => true, 'registrados' => $registrados];
    }

    /**
     * Encerramentos que valem (registrados ou creditados), com o valor e o crédito.
     *
     * @return list<array<string, mixed>>
     */
    private function registrados(Apostas $aposta, CarbonTimeZone $fuso): array
    {
        return $aposta->encerramentos()->queValem()->orderBy('id')->get()
            ->map(fn (ApostasEncerramentos $registrado) => [
                'modalidade' => $registrado->modalidade->value,
                'valor' => (string) $registrado->valor,
                'situacao' => $registrado->situacao->value,
                'creditar_em' => $this->data($registrado->creditar_em, $fuso),
            ])
            ->all();
    }

    /**
     * Primeiro nome e inicial do último sobrenome ("José Wilson Silva" → "José S."), FR-002.
     */
    public static function nome_abreviado(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY);

        if (count($partes) < 2) {
            return $partes[0] ?? '';
        }

        return $partes[0].' '.mb_strtoupper(mb_substr(end($partes), 0, 1)).'.';
    }

    /**
     * Aposta no estado final: decidida, encerrada (total) ou cancelada (FR-010).
     */
    public function final(Apostas $aposta): bool
    {
        return $aposta->situacao === SituacaoAposta::Cancelada || $aposta->resultado !== ResultadoAposta::Aguardando;
    }

    /**
     * Bloco de encerramento do dono (contracts/api.md): oferta assinada ou o motivo de não haver
     * oferta, parcial, automático, pedido em análise e encerramentos já registrados.
     *
     * @param  Collection<int, ApostasPalpites>|null  $palpites
     * @return array<string, mixed>
     */
    public function encerramento(Apostas $aposta, ?Collection $palpites, ?string $oferta_anterior, CarbonTimeZone $fuso): array
    {
        $oferta = $this->calculo->oferta($aposta, $palpites);
        $token = $oferta['disponivel'] ? $this->encerramento->token($aposta, $oferta) : null;
        $automatico = $aposta->encerramento_automatico()->first();
        $em_analise = $aposta->encerramentos()->where('situacao', SituacaoEncerramento::EmAnálise)->latest('id')->first();

        return [
            'disponivel' => $oferta['disponivel'],
            'motivo' => $oferta['motivo'],
            'oferta' => $oferta['oferta'],
            'variacao' => $this->variacao($oferta['oferta'], $oferta_anterior),
            'minimo' => $oferta['minimo'],
            'margem' => $oferta['margem'],
            'ao_vivo' => $oferta['ao_vivo'],
            'token_oferta' => $token['token'] ?? null,
            'expira_em' => isset($token) ? $this->data($token['expira_em'], $fuso) : null,
            'segundos_validade' => $this->configuracoes->segundos_validade(),
            'minutos_credito' => $this->configuracoes->minutos_janela(),
            'parcial' => $this->calculo->parcial($aposta),
            'automatico' => [
                'disponivel' => $this->configuracoes->automatico_habilitado(),
                'ativo' => $automatico !== null
                    ? ['valor_alvo' => (string) $automatico->valor_alvo, 'criado_em' => $this->data($automatico->created_at, $fuso)]
                    : null,
            ],
            'em_analise' => $em_analise !== null
                ? ['chave' => $em_analise->chave_idempotencia, 'segundos_restantes' => (int) max(0, ceil(now()->diffInSeconds($em_analise->created_at->copy()->addSeconds($aposta->delay_ao_vivo()), false)))]
                : null,
            'final' => false,
            'registrados' => $this->registrados($aposta, $fuso),
        ];
    }

    /**
     * A oferta subiu ou caiu desde a anterior que a página mostrou (FR-023).
     */
    private function variacao(?string $oferta, ?string $anterior): ?string
    {
        if ($oferta === null || $anterior === null || ! is_numeric($anterior)) {
            return null;
        }

        return match (bccomp($oferta, bcadd($anterior, '0', 2), 2)) {
            1 => 'subiu',
            -1 => 'caiu',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $cotacao  situação do palpite em aberto (CalculoEncerramento)
     * @return array<string, mixed>
     */
    private function palpite(ApostasPalpites $palpite, ?array $cotacao, CarbonTimeZone $fuso): array
    {
        if ($palpite->e_especial()) {
            return $this->palpite_especial($palpite, $fuso);
        }

        $confronto = $palpite->confronto;
        $estado = $this->estado($palpite, $cotacao);

        return [
            'id' => $palpite->id,
            'apostas_blocos_id' => $palpite->apostas_blocos_id,
            'confrontos_id' => $palpite->confrontos_id,
            'situacao' => $palpite->situacao->value,
            'resultado' => $palpite->resultado->value,
            'selo' => $estado === 'ao_vivo' && CalculoEncerramento::em_aberto($palpite) ? $this->selo($palpite) : null,
            'estado' => $estado,
            'campeonato' => $palpite->campeonato?->nome,
            'data_inicio' => $this->data($confronto?->data_inicio, $fuso),
            'time_casa' => $confronto?->time_casa,
            'time_fora' => $confronto?->time_fora,
            'escudo_casa' => $confronto?->ao_vivo?->escudo_casa ?? $confronto?->escudo_casa,
            'escudo_fora' => $confronto?->ao_vivo?->escudo_fora ?? $confronto?->escudo_fora,
            'esporte' => $palpite->esporte,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'mercado' => NomesCotacoes::nome($palpite->codigo_cotacao, $palpite->jogador_tipo),
            'jogador' => $palpite->jogador?->nome,
            'jogador_tipo' => $palpite->jogador_tipo,
            'cotacao' => (string) $palpite->cotacao_final,
            'cotacao_atual' => $cotacao['cotacao_atual'] ?? null,
            ...$this->andamento($palpite),
            'minutos_para_inicio' => $estado === 'nao_iniciado' && $confronto?->data_inicio !== null
                ? (int) max(0, ceil(now()->diffInMinutes($confronto->data_inicio, false)))
                : null,
        ];
    }

    /**
     * Estado do palpite na página: cancelado, encerrado (resultado gravado ou palpite decidido),
     * ao vivo (o jogo já começou) ou não iniciado.
     *
     * @param  array<string, mixed>|null  $cotacao
     */
    private function estado(ApostasPalpites $palpite, ?array $cotacao): string
    {
        return match (true) {
            $palpite->situacao === SituacaoPalpite::Cancelado => 'cancelado',
            $palpite->resultado !== ResultadoAposta::Aguardando, (bool) $palpite->confronto?->resultado?->apuravel() => 'encerrado',
            (bool) ($cotacao['iniciado'] ?? false) => 'ao_vivo',
            default => 'nao_iniciado',
        };
    }

    /**
     * Selo pelo placar atual, com as mesmas regras da apuração (R-06). Jogador e dado ausente
     * ficam "Aguardando".
     */
    private function selo(ApostasPalpites $palpite): string
    {
        $jogo = $palpite->confronto?->ao_vivo;

        if ($jogo === null || $palpite->codigo_cotacao === CodigosCotacao::JOGADOR) {
            return self::SELO_AGUARDANDO;
        }

        return match ($this->regras->avaliar($palpite->codigo_cotacao, PlacarConfronto::do_ao_vivo($jogo))) {
            ResultadoAposta::Vencedor => self::SELO_GANHANDO,
            ResultadoAposta::Perdedor => self::SELO_PERDENDO,
            default => self::SELO_AGUARDANDO,
        };
    }

    /**
     * Placar e período como no comprovante (spec 009): o resultado gravado ou, com o jogo em
     * andamento, o ao vivo. Gols por tempo e escanteios só no futebol (FR-004).
     *
     * @return array<string, mixed>
     */
    private function andamento(ApostasPalpites $palpite): array
    {
        $resultado = $palpite->confronto?->resultado;
        $jogo = $resultado ?? $palpite->confronto?->ao_vivo;
        $futebol = $palpite->esporte === self::FUTEBOL;

        if ($jogo === null) {
            return ['periodo' => null, 'minuto' => null, 'placar_casa' => null, 'placar_fora' => null, 'primeiro_tempo' => null, 'segundo_tempo' => null, 'escanteios' => null];
        }

        return [
            'periodo' => $jogo->situacao?->value,
            'minuto' => $resultado === null ? $jogo->minuto : null,
            'placar_casa' => $jogo->placar_casa,
            'placar_fora' => $jogo->placar_fora,
            'primeiro_tempo' => $futebol ? $this->tempo($jogo->gols_primeiro_tempo_casa, $jogo->gols_primeiro_tempo_fora) : null,
            'segundo_tempo' => $futebol ? $this->tempo($jogo->gols_segundo_tempo_casa, $jogo->gols_segundo_tempo_fora) : null,
            'escanteios' => $futebol && $jogo->escanteios_casa !== null && $jogo->escanteios_fora !== null
                ? (int) $jogo->escanteios_casa + (int) $jogo->escanteios_fora
                : null,
        ];
    }

    /**
     * @return array{casa: int, fora: int}|null
     */
    private function tempo(mixed $casa, mixed $fora): ?array
    {
        return $casa !== null && $fora !== null ? ['casa' => (int) $casa, 'fora' => (int) $fora] : null;
    }

    /**
     * Palpite especial no formato do comprovante: "Vencedor" e a categoria no lugar dos times.
     *
     * @return array<string, mixed>
     */
    private function palpite_especial(ApostasPalpites $palpite, CarbonTimeZone $fuso): array
    {
        $decidido = $palpite->resultado !== ResultadoAposta::Aguardando;

        return [
            'id' => $palpite->id,
            'apostas_blocos_id' => null,
            'confrontos_id' => null,
            'situacao' => $palpite->situacao->value,
            'resultado' => $palpite->resultado->value,
            'selo' => null,
            'estado' => $palpite->situacao === SituacaoPalpite::Cancelado ? 'cancelado' : ($decidido ? 'encerrado' : 'nao_iniciado'),
            'campeonato' => $palpite->especial?->nome,
            'data_inicio' => $this->data($palpite->especial?->data_limite, $fuso),
            'time_casa' => 'Vencedor',
            'time_fora' => $palpite->especial?->nome,
            'escudo_casa' => null,
            'escudo_fora' => null,
            'esporte' => $palpite->esporte,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'mercado' => $palpite->opcao_especial?->nome,
            'jogador' => null,
            'jogador_tipo' => null,
            'cotacao' => (string) $palpite->cotacao_final,
            'cotacao_atual' => null,
            'periodo' => null,
            'minuto' => null,
            'placar_casa' => null,
            'placar_fora' => null,
            'primeiro_tempo' => null,
            'segundo_tempo' => null,
            'escanteios' => null,
            'minutos_para_inicio' => null,
        ];
    }

    /**
     * Contagem por estado e segmentos da barra: cada palpite fora de bloco e cada bloco conta um
     * (FR-002). Estados: decidido, ganhando, perdendo, aguardando ou nao_iniciado.
     *
     * @param  list<array<string, mixed>>  $itens
     * @param  Collection<int, ApostasBlocos>  $blocos
     * @return array<string, mixed>
     */
    private function progresso(array $itens, Collection $blocos): array
    {
        $unidades = [];

        foreach ($itens as $item) {
            $chave = $item['apostas_blocos_id'] !== null ? 'bloco:'.$item['apostas_blocos_id'] : 'palpite:'.$item['id'];
            $unidades[$chave][] = $item;
        }

        $segmentos = [];

        foreach ($unidades as $chave => $grupo) {
            $bloco = str_starts_with($chave, 'bloco:') ? $blocos->firstWhere('id', (int) substr($chave, 6)) : null;
            $segmentos[] = $this->estado_unidade($grupo, $bloco);
        }

        $contagem = array_count_values(array_column($segmentos, 'estado'));

        return [
            'decididos' => $contagem['decidido'] ?? 0,
            'ganhando' => $contagem['ganhando'] ?? 0,
            'perdendo' => $contagem['perdendo'] ?? 0,
            'aguardando' => $contagem['aguardando'] ?? 0,
            'nao_iniciados' => $contagem['nao_iniciado'] ?? 0,
            'segmentos' => $segmentos,
        ];
    }

    /**
     * Estado de um palpite ou de um bloco Criar Aposta (pior estado entre os palpites dele).
     *
     * @param  list<array<string, mixed>>  $grupo
     * @return array{estado: string, resultado: ?string}
     */
    private function estado_unidade(array $grupo, ?ApostasBlocos $bloco): array
    {
        $ativos = array_values(array_filter($grupo, fn (array $item) => $item['situacao'] !== SituacaoPalpite::Cancelado->value));
        $resultados = array_column($ativos, 'resultado');

        if ($ativos === [] || $bloco?->situacao === SituacaoBloco::Anulado) {
            return ['estado' => 'decidido', 'resultado' => ResultadoAposta::Devolvido->value];
        }

        if (in_array(ResultadoAposta::Perdedor->value, $resultados, true)) {
            return ['estado' => 'decidido', 'resultado' => ResultadoAposta::Perdedor->value];
        }

        $abertos = array_filter($ativos, fn (array $item) => $item['resultado'] === ResultadoAposta::Aguardando->value);

        if ($abertos === []) {
            return ['estado' => 'decidido', 'resultado' => in_array(ResultadoAposta::Vencedor->value, $resultados, true) ? ResultadoAposta::Vencedor->value : ResultadoAposta::Devolvido->value];
        }

        $selos = array_column($abertos, 'selo');
        $estados = array_column($abertos, 'estado');

        $estado = match (true) {
            in_array(self::SELO_PERDENDO, $selos, true) => 'perdendo',
            in_array('nao_iniciado', $estados, true) => 'nao_iniciado',
            in_array(self::SELO_AGUARDANDO, $selos, true) || in_array(null, $selos, true) => 'aguardando',
            default => 'ganhando',
        };

        return ['estado' => $estado, 'resultado' => null];
    }

    /**
     * Canais do tempo real: o da aposta e os dos jogos ainda em aberto.
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @return array{aposta: string, confrontos: list<string>}
     */
    private function canais(Apostas $aposta, Collection $palpites): array
    {
        $jogos = $palpites
            ->filter(fn (ApostasPalpites $palpite) => ! $palpite->e_especial() && CalculoEncerramento::em_aberto($palpite))
            ->pluck('confrontos_id')
            ->unique()
            ->map(fn (int $id) => "confrontos.{$id}")
            ->values()
            ->all();

        return ['aposta' => "apostas.{$aposta->codigo}", 'confrontos' => $jogos];
    }

    private function data(?Carbon $data, CarbonTimeZone $fuso): ?string
    {
        return $data?->copy()->setTimezone($fuso)->toIso8601String();
    }
}
