<?php

namespace App\Services;

use App\Enums\AceitarAlteracoes;
use App\Enums\FormaPagamento;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoAposta;
use App\Exceptions\CotacoesAlteradasException;
use App\Exceptions\RegraApostaException;
use App\Jobs\DecidirApostaAoVivo;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Clientes;
use App\Models\Configuracoes;
use App\Models\EspeciaisOpcoes;
use App\Models\UsuariosConfiguracoes;
use App\Support\Apostador;
use App\Support\CodigoAposta;
use App\Support\CodigosCotacao;
use App\Support\FusoSistema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Criação da aposta nas três formas (visitante, vendedor e cliente). Tudo é recalculado no
 * servidor: cotações, prêmio, comissão, carteira e horários (FR-005). Dinheiro e limites mudam
 * numa transação, com os bloqueios na ordem fixa da research R-07.
 */
class CriacaoApostas
{
    private const TENTATIVAS_CODIGO = 5;

    private const TAMANHO_USER_AGENT = 255;

    public function __construct(
        private CalculoCotacoes $cotacoes,
        private RegrasAposta $regras,
        private ConferenciaCotacoes $conferencia,
        private CalculoPremio $premio,
        private AssinaturaApostas $assinatura,
        private EscolhaCarteira $carteira,
        private RolloverClientes $rollover,
        private SaldoClientes $saldo,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  pedido validado (ApostasRequest::dados)
     * @return array{0: Apostas, 1: bool} a aposta e se é um envio repetido (idempotência)
     *
     * @throws RegraApostaException|CotacoesAlteradasException
     */
    public function criar(Apostador $apostador, array $dados, ?string $ip, ?string $user_agent): array
    {
        $existente = $this->aposta_da_chave($apostador, $dados['chave_idempotencia'], $ip);

        if ($existente !== null) {
            return [$existente, true];
        }

        $preparo = $this->preparar($apostador, $dados);

        $aposta = DB::transaction(fn () => $this->gravar($apostador, $dados, $preparo, $ip, $user_agent));

        if ($aposta->situacao === SituacaoAposta::EmAnálise) {
            DecidirApostaAoVivo::dispatch($aposta->id)
                ->onQueue(DecidirApostaAoVivo::FILA)
                ->delay(now()->addSeconds($apostador->delay_ao_vivo()));
        }

        return [$aposta, false];
    }

    /**
     * Aposta já gravada com a chave de idempotência (FR-009): devolvida ao mesmo apostador; para
     * o visitante, só a Pendente criada pelo mesmo IP.
     *
     * @throws RegraApostaException quando a chave é de outro apostador
     */
    public function aposta_da_chave(Apostador $apostador, string $chave, ?string $ip): ?Apostas
    {
        $existente = Apostas::where('chave_idempotencia', $chave)->first();

        if ($existente === null) {
            return null;
        }

        $mesmo_apostador = $apostador->e_visitante()
            ? $existente->situacao === SituacaoAposta::Pendente && $existente->ip_criacao === $ip
                && $existente->usuarios_id === null && $existente->clientes_id === null
            : $apostador->e_dono($existente->usuarios_id, $existente->clientes_id);

        if (! $mesmo_apostador) {
            throw new RegraApostaException('Chave de idempotência já usada.');
        }

        return $existente;
    }

    /**
     * Confere tudo sem gravar: apostador, palpites, cotações (indisponíveis e alteradas), prêmio e
     * limites da aposta.
     *
     * @param  array<string, mixed>  $dados
     * @return array{palpites: list<array<string, mixed>>, calculo: array<string, string>, valor: string, aceitar: AceitarAlteracoes, ao_vivo: bool}
     *
     * @throws RegraApostaException|CotacoesAlteradasException
     */
    public function preparar(Apostador $apostador, array $dados): array
    {
        $this->regras->conferir_apostador($apostador);

        $palpites = $this->montar($apostador, $dados['palpites']);

        $this->regras->conferir_palpites($apostador, $palpites);
        $this->conferencia->garantir_disponiveis($palpites);

        $valor = bcadd((string) $dados['valor'], '0', 2);
        $aceitar = AceitarAlteracoes::from($dados['aceitar_alteracoes'] ?? AceitarAlteracoes::Nenhuma->value);
        $calculo = $this->calcular($apostador, $valor, array_column($palpites, 'cotacao_atual'));
        $alteracoes = $this->conferencia->alteracoes($palpites, $aceitar);

        if ($alteracoes !== []) {
            throw new CotacoesAlteradasException($alteracoes, $calculo);
        }

        $this->regras->conferir_limites($apostador, $valor, count($palpites), $calculo['cotacao_total']);

        return [
            'palpites' => $palpites,
            'calculo' => $calculo,
            'valor' => $valor,
            'aceitar' => $aceitar,
            'ao_vivo' => in_array(true, array_map(fn (array $palpite) => $palpite['confrontos_ao_vivo_id'] !== null, $palpites), true),
        ];
    }

    /**
     * Prêmio pelas regras do apostador (FR-025).
     *
     * @param  list<string>  $cotacoes
     * @return array{cotacao_total: string, premio: string, valor_acrescido: string, total_a_pagar: string}
     */
    public function calcular(Apostador $apostador, string $valor, array $cotacoes): array
    {
        return $this->premio->calcular(
            $valor,
            $cotacoes,
            $apostador->multiplicador(),
            $apostador->premio_maximo(),
            $apostador->ganho_multiplo_palpites(),
        );
    }

    /**
     * Carrega os jogos, jogadores e cotações atuais (para o público do apostador) dos palpites
     * pedidos. Jogo inexistente não interrompe: o palpite volta com o motivo.
     *
     * @param  list<array<string, mixed>>  $pedidos
     * @return list<array<string, mixed>>
     */
    public function montar(Apostador $apostador, array $pedidos): array
    {
        $jogos_ao_vivo = DB::table('confrontos_ao_vivo')
            ->whereIn('id', array_filter(array_column($pedidos, 'confrontos_ao_vivo_id')))
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $ids_confrontos = array_map(fn (array $pedido) => match (true) {
            isset($pedido['especiais_opcoes_id']) => 0,
            isset($pedido['confrontos_ao_vivo_id']) => (int) ($jogos_ao_vivo[$pedido['confrontos_ao_vivo_id']]->confrontos_id ?? 0),
            default => (int) $pedido['confrontos_id'],
        }, $pedidos);

        // opções especiais pedidas, com a categoria (removidas também, para dizer o motivo)
        $opcoes_especiais = EspeciaisOpcoes::withTrashed()
            ->with('especial')
            ->whereIn('id', array_filter(array_column($pedidos, 'especiais_opcoes_id')))
            ->get()
            ->keyBy('id');

        $confrontos = DB::table('confrontos as co')
            ->join('campeonatos as ca', 'ca.id', '=', 'co.campeonatos_id')
            ->whereIn('co.id', array_filter($ids_confrontos))
            ->whereNull('co.deleted_at')
            ->whereNull('ca.deleted_at')
            ->get(['co.*', 'ca.ativo as campeonato_ativo', 'ca.nome as campeonato_nome'])
            ->keyBy('id');

        $em_andamento = DB::table('confrontos_ao_vivo')
            ->whereIn('confrontos_id', array_filter($ids_confrontos))
            ->whereNull('deleted_at')
            ->whereIn('situacao', RegrasExibicao::situacoes_ao_vivo())
            ->pluck('confrontos_id')
            ->flip();

        $jogadores = DB::table('confrontos_jogadores')
            ->whereIn('id', array_filter(array_column($pedidos, 'confrontos_jogadores_id')))
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        [$cotacoes_pre_jogo, $cotacoes_ao_vivo, $cotacoes_jogadores] = $this->cotacoes_atuais($apostador, $pedidos, $confrontos, $jogos_ao_vivo, $jogadores);
        $travado = $this->travamentos($jogos_ao_vivo);

        $palpites = [];

        foreach ($pedidos as $indice => $pedido) {
            if (isset($pedido['especiais_opcoes_id'])) {
                $palpites[] = $this->palpite_especial($indice, $pedido, $opcoes_especiais[(int) $pedido['especiais_opcoes_id']] ?? null);

                continue;
            }

            $id_ao_vivo = isset($pedido['confrontos_ao_vivo_id']) ? (int) $pedido['confrontos_ao_vivo_id'] : null;
            $ao_vivo = $id_ao_vivo !== null ? ($jogos_ao_vivo[$id_ao_vivo] ?? null) : null;
            $confronto = $confrontos[$ids_confrontos[$indice]] ?? null;
            $codigo = $pedido['codigo_cotacao'];
            $id_jogador = isset($pedido['confrontos_jogadores_id']) ? (int) $pedido['confrontos_jogadores_id'] : null;
            $jogador = $id_jogador !== null ? ($jogadores[$id_jogador] ?? null) : null;
            $nao_encontrado = $confronto === null || ($id_ao_vivo !== null && $ao_vivo === null);

            [$original, $atual] = match (true) {
                $nao_encontrado => ['0', 0.0],
                $codigo === CodigosCotacao::JOGADOR => [$jogador->odd ?? '0', $jogador !== null ? ($cotacoes_jogadores[$jogador->id] ?? 0.0) : 0.0],
                $ao_vivo !== null => [json_decode($ao_vivo->cotacoes, true)[$codigo] ?? '0', $travado[$ao_vivo->id] ? 0.0 : ($cotacoes_ao_vivo[$ao_vivo->id][$codigo] ?? 0.0)],
                default => [json_decode($confronto->cotacoes, true)[$codigo] ?? '0', $cotacoes_pre_jogo[$confronto->id][$codigo] ?? 0.0],
            };

            $palpites[] = [
                'indice' => $indice,
                'confrontos_id' => $confronto?->id !== null ? (int) $confronto->id : (int) ($pedido['confrontos_id'] ?? 0),
                'confrontos_ao_vivo_id' => $id_ao_vivo,
                'campeonatos_id' => $confronto !== null ? (int) $confronto->campeonatos_id : null,
                'especiais_id' => null,
                'especiais_opcoes_id' => null,
                'esporte' => $ao_vivo->esporte ?? $confronto->esporte ?? '',
                'codigo_cotacao' => $codigo,
                'confrontos_jogadores_id' => $id_jogador,
                'jogador_tipo' => $jogador?->tipo,
                'cotacao_vista' => bcadd((string) $pedido['cotacao_vista'], '0', 2),
                'cotacao_original' => bcadd((string) $original, '0', 2),
                'cotacao_atual' => number_format($atual, 2, '.', ''),
                'travado' => $ao_vivo !== null && $travado[$ao_vivo->id],
                'em_andamento' => $confronto !== null && isset($em_andamento[$confronto->id]),
                'confronto' => $confronto,
                'ao_vivo' => $ao_vivo,
                'jogador' => $jogador,
                'especial' => null,
                'opcao_especial' => null,
                'motivo' => $nao_encontrado ? 'O confronto informado não foi encontrado.' : null,
            ];
        }

        return $palpites;
    }

    /**
     * Palpite numa opção especial: a cotação é a fixa da opção, sem porcentagens nem teto (spec
     * 006, FR-016). Opção que não aceita mais palpite fica com cotação 0 (indisponível); o motivo
     * vem das regras da aposta.
     *
     * @param  array<string, mixed>  $pedido
     * @return array<string, mixed>
     */
    private function palpite_especial(int $indice, array $pedido, ?EspeciaisOpcoes $opcao): array
    {
        $especial = $opcao?->especial;
        $disponivel = $opcao !== null && ! $opcao->trashed() && $opcao->ativo && $especial !== null && ! $especial->trashed() && $especial->aceita_palpites();
        $cotacao = $opcao !== null ? (string) $opcao->cotacao : '0';

        return [
            'indice' => $indice,
            'confrontos_id' => null,
            'confrontos_ao_vivo_id' => null,
            'campeonatos_id' => null,
            'especiais_id' => $especial?->id,
            'especiais_opcoes_id' => (int) $pedido['especiais_opcoes_id'],
            'esporte' => 'ESPECIAL',
            'codigo_cotacao' => CodigosCotacao::ESPECIAL,
            'confrontos_jogadores_id' => null,
            'jogador_tipo' => null,
            'cotacao_vista' => bcadd((string) $pedido['cotacao_vista'], '0', 2),
            'cotacao_original' => bcadd($cotacao, '0', 2),
            'cotacao_atual' => $disponivel ? bcadd($cotacao, '0', 2) : '0.00',
            'travado' => false,
            'em_andamento' => false,
            'confronto' => null,
            'ao_vivo' => null,
            'jogador' => null,
            'especial' => $especial,
            'opcao_especial' => $opcao,
            'motivo' => $opcao === null ? 'A opção especial informada não foi encontrada.' : null,
        ];
    }

    /**
     * Confirma a aposta como Ativa (FR-023, FR-024, FR-043 a FR-048): o vendedor abate os limites
     * de venda e recebe a comissão; o cliente paga com uma carteira e soma no rollover. Usada na
     * criação, na decisão do ao vivo e na validação do código. Chamar dentro de uma transação,
     * depois de bloquear a aposta e os confrontos.
     *
     * @throws RegraApostaException
     */
    public function confirmar(Apostador $apostador, Apostas $aposta, int $quantidade_palpites, bool $ao_vivo): void
    {
        if ($apostador->e_vendedor()) {
            $this->confirmar_vendedor($apostador, $aposta, $quantidade_palpites, $ao_vivo);
        } else {
            $this->confirmar_cliente($apostador, $aposta, $quantidade_palpites);
        }

        $aposta->situacao = SituacaoAposta::Ativa;
        $aposta->confirmada_em = now();
        $aposta->save();

        $aposta->assinatura = $this->assinatura->assinar($aposta);
        $aposta->save();
    }

    /**
     * Limite de valor apostado por confronto (FR-022): bloqueia as linhas dos jogos em ordem de id
     * e soma as apostas Ativas e Em análise de cada um.
     *
     * @param  list<array<string, mixed>>  $palpites
     *
     * @throws RegraApostaException
     */
    public function garantir_limite_por_confronto(array $palpites, string $valor, ?int $ignorar_apostas_id = null): void
    {
        // palpite especial não tem confronto nem limite por categoria
        $pre_jogo = array_filter($palpites, fn (array $palpite) => $palpite['confrontos_ao_vivo_id'] === null && $palpite['confrontos_id'] !== null);
        $ao_vivo = array_filter($palpites, fn (array $palpite) => $palpite['confrontos_ao_vivo_id'] !== null);

        $this->conferir_limite_da_tabela('confrontos', 'confrontos_id', array_column($pre_jogo, 'confrontos_id'), $palpites, $valor, $ignorar_apostas_id);
        $this->conferir_limite_da_tabela('confrontos_ao_vivo', 'confrontos_ao_vivo_id', array_column($ao_vivo, 'confrontos_ao_vivo_id'), $palpites, $valor, $ignorar_apostas_id);
    }

    /**
     * Grava os palpites da aposta com a cotação final (a atual, aceita pelo apostador).
     *
     * @param  list<array<string, mixed>>  $palpites
     */
    public function gravar_palpites(Apostas $aposta, array $palpites): void
    {
        foreach ($palpites as $palpite) {
            ApostasPalpites::create([
                'apostas_id' => $aposta->id,
                'confrontos_id' => $palpite['confrontos_id'],
                'confrontos_ao_vivo_id' => $palpite['confrontos_ao_vivo_id'],
                'campeonatos_id' => $palpite['campeonatos_id'],
                'especiais_id' => $palpite['especiais_id'] ?? null,
                'especiais_opcoes_id' => $palpite['especiais_opcoes_id'] ?? null,
                'esporte' => $palpite['esporte'],
                'codigo_cotacao' => $palpite['codigo_cotacao'],
                'confrontos_jogadores_id' => $palpite['confrontos_jogadores_id'],
                'jogador_tipo' => $palpite['jogador_tipo'],
                'cotacao_vista' => $palpite['cotacao_vista'],
                'cotacao_original' => $palpite['cotacao_original'],
                'cotacao_final' => $palpite['cotacao_final'] ?? $palpite['cotacao_atual'],
                'dados_ao_vivo_envio' => $palpite['ao_vivo'] !== null ? self::fotografia($palpite['ao_vivo']) : null,
                'situacao' => SituacaoPalpite::Ativo,
            ]);
        }
    }

    /**
     * Fotografia do jogo ao vivo para comparar o envio com a decisão (R-02).
     *
     * @return array<string, mixed>
     */
    public static function fotografia(object $jogo): array
    {
        // números sempre inteiros (ou nulos), para a comparação estrita do envio com a decisão
        $numero = fn (mixed $valor) => $valor === null ? null : (int) $valor;

        return [
            'placar_casa' => (int) $jogo->placar_casa,
            'placar_fora' => (int) $jogo->placar_fora,
            'gols_primeiro_tempo_casa' => $numero($jogo->gols_primeiro_tempo_casa),
            'gols_primeiro_tempo_fora' => $numero($jogo->gols_primeiro_tempo_fora),
            'gols_segundo_tempo_casa' => $numero($jogo->gols_segundo_tempo_casa),
            'gols_segundo_tempo_fora' => $numero($jogo->gols_segundo_tempo_fora),
            'escanteios_casa' => $numero($jogo->escanteios_casa),
            'escanteios_fora' => $numero($jogo->escanteios_fora),
            'minuto' => (int) $jogo->minuto,
            'situacao' => (string) $jogo->situacao,
            'ultima_atualizacao_em' => Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->toIso8601String(),
        ];
    }

    /**
     * Uma aposta Em análise por apostador (FR-032).
     *
     * @throws RegraApostaException
     */
    public function garantir_uma_analise(Apostador $apostador, ?int $ignorar_apostas_id = null): void
    {
        $existe = Apostas::where('situacao', SituacaoAposta::EmAnálise)
            ->when($apostador->e_vendedor(), fn ($c) => $c->where('usuarios_id', $apostador->usuario()->id)->whereNull('clientes_id'))
            ->when($apostador->e_cliente(), fn ($c) => $c->where('clientes_id', $apostador->cliente_logado()->id))
            ->when($ignorar_apostas_id !== null, fn ($c) => $c->where('id', '!=', $ignorar_apostas_id))
            ->exists();

        if ($existe) {
            throw new RegraApostaException('Você já tem uma aposta do ao vivo em análise. Aguarde a decisão para fazer outra.');
        }
    }

    /**
     * Valor máximo apostado por dia do cliente (FR-021): apostas Ativas e Em análise do dia, no
     * fuso do sistema.
     *
     * @throws RegraApostaException
     */
    public function garantir_valor_diario(Apostador $apostador, string $valor, ?int $ignorar_apostas_id = null): void
    {
        $apostado = (string) Apostas::where('clientes_id', $apostador->cliente_logado()->id)
            ->whereIn('situacao', [SituacaoAposta::Ativa, SituacaoAposta::EmAnálise])
            ->where('created_at', '>=', FusoSistema::inicio_do_dia())
            ->when($ignorar_apostas_id !== null, fn ($c) => $c->where('id', '!=', $ignorar_apostas_id))
            ->sum('valor');

        $maximo = (string) $apostador->configuracao->valor_maximo_diario;

        if (bccomp(bcadd($apostado, $valor, 2), $maximo, 2) > 0) {
            $restante = bcsub($maximo, $apostado, 2);

            throw new RegraApostaException('Restam '.RegrasAposta::reais(bccomp($restante, '0', 2) > 0 ? $restante : '0').' do seu valor máximo apostado por dia.');
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  array<string, mixed>  $preparo
     */
    private function gravar(Apostador $apostador, array $dados, array $preparo, ?string $ip, ?string $user_agent): Apostas
    {
        $palpites = $preparo['palpites'];
        $ao_vivo = $preparo['ao_vivo'];

        // ordem de bloqueio (R-07): confrontos → vendedor ou cliente
        if (! $apostador->e_visitante()) {
            $this->garantir_limite_por_confronto($palpites, $preparo['valor']);
            $this->bloquear_apostador($apostador);
        }

        $aposta = new Apostas([
            'codigo' => $this->novo_codigo(),
            'chave_idempotencia' => $dados['chave_idempotencia'],
            'nome' => $dados['nome'],
            'tipo' => $ao_vivo ? TipoAposta::AoVivo : TipoAposta::PréJogo,
            'aceitar_alteracoes' => $preparo['aceitar'],
            'valor' => $preparo['valor'],
            'cotacao_total' => $preparo['calculo']['cotacao_total'],
            'premio' => $preparo['calculo']['premio'],
            'valor_acrescido' => $preparo['calculo']['valor_acrescido'],
            'multiplicador' => $apostador->multiplicador(),
            'premio_maximo' => $apostador->premio_maximo(),
            'ganho_multiplo_palpites' => $apostador->ganho_multiplo_palpites(),
            'usuarios_id' => $apostador->usuario()?->id,
            'clientes_id' => $apostador->cliente_logado()?->id,
            'recebida_em' => now(),
            'ip_criacao' => $ip,
            'user_agent_criacao' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
        ]);

        if ($apostador->e_visitante()) {
            $aposta->situacao = SituacaoAposta::Pendente;
            $aposta->expira_em = now()->addHours((int) $apostador->configuracao->horas_validade_codigo);
            $aposta->save();
            $this->gravar_palpites($aposta, $palpites);

            return $aposta;
        }

        if ($apostador->e_cliente()) {
            $this->garantir_valor_diario($apostador, $preparo['valor']);
        }

        if ($ao_vivo) {
            // o delay corre uma vez; saldo e limites só são movidos na decisão (FR-031)
            $this->garantir_uma_analise($apostador);
            $aposta->situacao = SituacaoAposta::EmAnálise;
            $aposta->save();
            $this->gravar_palpites($aposta, $palpites);

            return $aposta;
        }

        $aposta->situacao = SituacaoAposta::Ativa;
        $aposta->save();
        $this->gravar_palpites($aposta, $palpites);
        $this->confirmar($apostador, $aposta, count($palpites), false);

        return $aposta;
    }

    /**
     * Bloqueia a linha que guarda o dinheiro ou os limites do apostador, serializando as apostas
     * simultâneas dele (R-07).
     */
    private function bloquear_apostador(Apostador $apostador): void
    {
        if ($apostador->e_vendedor()) {
            UsuariosConfiguracoes::where('usuarios_id', $apostador->usuario()->id)->lockForUpdate()->first();
        } else {
            Clientes::withTrashed()->lockForUpdate()->find($apostador->cliente_logado()->id);
        }
    }

    /**
     * @throws RegraApostaException
     */
    private function confirmar_vendedor(Apostador $apostador, Apostas $aposta, int $quantidade_palpites, bool $ao_vivo): void
    {
        $configuracao = UsuariosConfiguracoes::where('usuarios_id', $apostador->usuario()->id)->lockForUpdate()->firstOrFail();
        $valor = (string) $aposta->valor;
        $limite_por_quantidade = $quantidade_palpites === 1 ? 'limite_simples' : 'limite_duplo';
        $nomes_limites = ['limite_simples' => 'simples', 'limite_duplo' => 'duplo', 'limite_geral' => 'geral'];

        foreach ([$limite_por_quantidade, 'limite_geral'] as $limite) {
            if (bccomp((string) $configuracao->{$limite}, $valor, 2) < 0) {
                throw new RegraApostaException('Restam '.RegrasAposta::reais((string) $configuracao->{$limite})." do seu limite {$nomes_limites[$limite]}.");
            }
        }

        foreach ([$limite_por_quantidade, 'limite_geral'] as $limite) {
            $configuracao->{$limite} = bcsub((string) $configuracao->{$limite}, $valor, 2);
        }

        $configuracao->save();

        $percentual = $configuracao->percentual_comissao($quantidade_palpites, $ao_vivo);

        $aposta->forma_pagamento = FormaPagamento::Dinheiro;
        $aposta->percentual_comissao = $percentual;
        $aposta->comissao = bcdiv(bcmul($valor, $percentual, 10), '100', 2);
        $aposta->comissao_por_premio = $configuracao->comissao_por_premio;
        $aposta->tempo_cancelamento_aposta = $configuracao->tempo_cancelamento_aposta;
        $aposta->usuarios_id = $apostador->usuario()->id;
    }

    /**
     * @throws RegraApostaException
     */
    private function confirmar_cliente(Apostador $apostador, Apostas $aposta, int $quantidade_palpites): void
    {
        $cliente = Clientes::withTrashed()->lockForUpdate()->findOrFail($apostador->cliente_logado()->id);
        $valor = (string) $aposta->valor;
        $carteira = $this->carteira->escolher($cliente, $valor);
        $forma = FormaPagamento::da_carteira($carteira);

        if ($forma === FormaPagamento::PromoçãoEsportes) {
            $this->rollover->conferir_regras_bonus($cliente, $valor, $quantidade_palpites, (string) $aposta->cotacao_total);
        }

        $aposta->forma_pagamento = $forma;
        $aposta->save();

        $this->saldo->debitar($cliente, $carteira, $valor, OrigemTransacao::Aposta, referencia_id: $aposta->id);
        $this->rollover->somar($aposta);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<array<string, mixed>>  $palpites
     *
     * @throws RegraApostaException
     */
    private function conferir_limite_da_tabela(string $tabela, string $coluna, array $ids, array $palpites, string $valor, ?int $ignorar_apostas_id): void
    {
        if ($ids === []) {
            return;
        }

        sort($ids);

        $limites = DB::table($tabela)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('limite_valor_apostado', 'id');
        $apostado = $this->valor_apostado($coluna, $ids, $ignorar_apostas_id);

        foreach ($ids as $id) {
            $soma = (string) ($apostado[$id] ?? '0');
            $limite = (string) ($limites[$id] ?? '0');

            if (bccomp(bcadd($soma, $valor, 2), $limite, 2) > 0) {
                $restante = bcsub($limite, $soma, 2);
                $palpite = collect($palpites)->firstWhere($coluna, $id);

                throw new RegraApostaException('Restam '.RegrasAposta::reais(bccomp($restante, '0', 2) > 0 ? $restante : '0')
                    .' de limite de aposta no confronto '.RegrasAposta::nome_confronto($palpite).'.');
            }
        }
    }

    /**
     * Soma das apostas Ativas e Em análise com cada jogo (pré-jogo ou ao vivo).
     *
     * @param  list<int>  $ids
     * @return Collection<int, string>
     */
    public function valor_apostado(string $coluna, array $ids, ?int $ignorar_apostas_id = null): Collection
    {
        return DB::table('apostas_palpites as ap')
            ->join('apostas as a', 'a.id', '=', 'ap.apostas_id')
            ->whereIn("ap.{$coluna}", $ids)
            ->when($coluna === 'confrontos_id', fn ($c) => $c->whereNull('ap.confrontos_ao_vivo_id'))
            ->where('ap.situacao', SituacaoPalpite::Ativo->value)
            ->whereNull('ap.deleted_at')
            ->whereNull('a.deleted_at')
            ->whereIn('a.situacao', [SituacaoAposta::Ativa->value, SituacaoAposta::EmAnálise->value])
            ->when($ignorar_apostas_id !== null, fn ($c) => $c->where('a.id', '!=', $ignorar_apostas_id))
            ->groupBy("ap.{$coluna}")
            ->selectRaw("ap.{$coluna} as jogo, SUM(a.valor) as total")
            ->pluck('total', 'jogo');
    }

    /**
     * Cotações atuais para o público, só dos códigos pedidos: [pré-jogo, ao vivo, jogadores].
     *
     * @param  list<array<string, mixed>>  $pedidos
     * @return array{0: array<int, array<string, float>>, 1: array<int, array<string, float>>, 2: array<int, float>}
     */
    private function cotacoes_atuais(Apostador $apostador, array $pedidos, Collection $confrontos, Collection $jogos_ao_vivo, Collection $jogadores): array
    {
        $codigos = array_values(array_unique(array_filter(
            array_column($pedidos, 'codigo_cotacao'),
            fn (string $codigo) => $codigo !== CodigosCotacao::JOGADOR && $codigo !== CodigosCotacao::ESPECIAL,
        )));

        $itens_pre_jogo = $confrontos->map(fn (object $confronto) => (object) [
            'id' => $confronto->id,
            'campeonatos_id' => $confronto->campeonatos_id,
            'confrontos_id' => $confronto->id,
            'cotacoes' => json_decode($confronto->cotacoes, true) ?? [],
        ])->values();

        $itens_ao_vivo = $jogos_ao_vivo->map(fn (object $jogo) => (object) [
            'id' => $jogo->id,
            'campeonatos_id' => $jogo->campeonatos_id,
            'confrontos_id' => $jogo->confrontos_id,
            'cotacoes' => json_decode($jogo->cotacoes, true) ?? [],
        ])->values();

        $itens_jogadores = $jogadores->map(fn (object $jogador) => (object) [
            'id' => $jogador->id,
            'campeonatos_id' => $confrontos[$jogador->confrontos_id]->campeonatos_id ?? null,
            'odd' => $jogador->odd,
        ])->values();

        return [
            $this->cotacoes->ajustar($apostador->publico, CalculoCotacoes::PRE_JOGO, $itens_pre_jogo, $codigos),
            $this->cotacoes->ajustar($apostador->publico, CalculoCotacoes::AO_VIVO, $itens_ao_vivo, $codigos),
            $this->cotacoes->ajustar_jogadores($apostador->publico, $itens_jogadores),
        ];
    }

    /**
     * Jogos do ao vivo travados agora: trava geral ou sem atualização há mais tempo que o limite.
     *
     * @return array<int, bool>
     */
    private function travamentos(Collection $jogos_ao_vivo): array
    {
        $configuracoes = Configuracoes::atual();
        $limite = now()->subSeconds($configuracoes->segundos_trava_ao_vivo);

        return $jogos_ao_vivo->map(fn (object $jogo) => $configuracoes->ao_vivo_travado
            || Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->lt($limite))->all();
    }

    private function novo_codigo(): string
    {
        for ($tentativa = 1; $tentativa <= self::TENTATIVAS_CODIGO; $tentativa++) {
            $codigo = CodigoAposta::gerar();

            if (! Apostas::withTrashed()->where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new RegraApostaException('Não foi possível gerar o código da aposta. Tente novamente.');
    }
}
