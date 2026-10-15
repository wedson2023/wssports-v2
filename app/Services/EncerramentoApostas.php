<?php

namespace App\Services;

use App\Enums\AceitarAlteracoes;
use App\Enums\Carteira;
use App\Enums\ModalidadeEncerramento;
use App\Enums\OrigemApuracao;
use App\Enums\OrigemTransacao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoEncerramentoAutomatico;
use App\Enums\TipoNotificacao;
use App\Enums\TipoOperacaoApuracao;
use App\Events\ApostaAtualizada;
use App\Exceptions\OfertaAlteradaException;
use App\Exceptions\RegraApostaException;
use App\Jobs\DecidirEncerramento;
use App\Models\Apostas;
use App\Models\ApostasEncerramentos;
use App\Models\ApostasEncerramentosAutomaticos;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Support\CodigoAposta;
use App\Support\FusoSistema;
use App\Support\TokenOferta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encerramento de aposta pelo cliente (spec 010, R-09 a R-11): pedido com a oferta assinada,
 * tempo de confirmação do ao vivo, decisão com a aposta travada, efeitos (resultado ou fração,
 * rollover, automático) e desfazimento. O crédito fica para depois da janela
 * (LiquidacaoEncerramentos).
 */
class EncerramentoApostas
{
    private const ESCALA = 10;

    private const TAMANHO_USER_AGENT = 255;

    public function __construct(
        private CalculoEncerramento $calculo,
        private ConfiguracoesEncerramento $configuracoes,
        private RolloverClientes $rollover,
        private SaldoClientes $saldo,
        private RegistroApuracao $registro,
        private NotificacoesPush $notificacoes,
    ) {}

    /**
     * Token e validade da oferta exibida no bilhete (R-09).
     *
     * @param  array<string, mixed>  $oferta  resultado de CalculoEncerramento::oferta, disponível
     * @return array{token: string, expira_em: \Illuminate\Support\Carbon}
     */
    public function token(Apostas $aposta, array $oferta): array
    {
        $expira_em = now()->addSeconds($this->configuracoes->segundos_validade());

        $token = TokenOferta::assinar([
            'apostas_id' => $aposta->id,
            'clientes_id' => $aposta->clientes_id,
            'oferta' => $oferta['oferta'],
            'minimo' => $oferta['minimo'],
            'margem' => $oferta['margem'],
            'ao_vivo' => $oferta['ao_vivo'],
            'fracao_restante' => (string) $aposta->fracao_restante,
            'palpites' => array_map(fn (array $palpite) => array_intersect_key($palpite, array_flip(['id', 'jogo', 'cotacao_atual', 'placar_casa', 'placar_fora', 'periodo'])), $oferta['fotografia']),
            'expira_em' => $expira_em->toIso8601String(),
        ]);

        return ['token' => $token, 'expira_em' => $expira_em];
    }

    /**
     * Pedido de encerramento (total ou parcial) do cliente ou do usuário do painel em acesso
     * assistido. Com jogo ao vivo em aberto, fica Em análise até o fim do tempo de confirmação;
     * sem jogo ao vivo, é decidido na hora.
     *
     * @param  array{modalidade: string, valor: string, token_oferta: string, chave_idempotencia: string, aceitar_alteracoes?: ?string}  $dados
     * @return array{0: ApostasEncerramentos, 1: bool} o encerramento e se é um reenvio
     *
     * @throws OfertaAlteradaException|RegraApostaException
     */
    public function pedir(Clientes $cliente, ?Usuarios $autor, string $codigo, array $dados, ?string $ip, ?string $user_agent): array
    {
        $existente = ApostasEncerramentos::where('chave_idempotencia', $dados['chave_idempotencia'])->first();

        if ($existente !== null) {
            if ($existente->clientes_id !== $cliente->id || $existente->aposta?->codigo !== CodigoAposta::normalizar($codigo)) {
                throw new RegraApostaException('Chave de idempotência já usada.');
            }

            return [$existente, true];
        }

        $aposta = Apostas::buscar_pelo_codigo($codigo)->where('clientes_id', $cliente->id)->first();

        abort_if($aposta === null, 404, 'Aposta não encontrada.');

        $token = TokenOferta::conferir($dados['token_oferta']);

        if ($token === null || (int) $token['apostas_id'] !== $aposta->id || (int) $token['clientes_id'] !== $cliente->id
            || bccomp((string) $token['fracao_restante'], (string) $aposta->fracao_restante, self::ESCALA) !== 0) {
            throw new OfertaAlteradaException;
        }

        $modalidade = ModalidadeEncerramento::from($dados['modalidade']);
        $valor_pedido = bcadd((string) $dados['valor'], '0', 2);
        $this->conferir_valor($aposta, $modalidade, $valor_pedido, $token);

        $encerramento = DB::transaction(function () use ($aposta, $cliente, $autor, $dados, $modalidade, $valor_pedido, $token, $ip, $user_agent) {
            $aposta = Apostas::lockForUpdate()->findOrFail($aposta->id);

            if ($aposta->encerramentos()->where('situacao', SituacaoEncerramento::EmAnálise)->exists()) {
                throw new RegraApostaException('Já existe um encerramento em análise para esta aposta.');
            }

            $encerramento = ApostasEncerramentos::create([
                ...$this->valores_antes($aposta),
                'apostas_id' => $aposta->id,
                'clientes_id' => $cliente->id,
                'usuarios_id' => $autor?->id,
                'modalidade' => $modalidade,
                'situacao' => SituacaoEncerramento::EmAnálise,
                'chave_idempotencia' => $dados['chave_idempotencia'],
                'aceitar_alteracoes' => AceitarAlteracoes::from($dados['aceitar_alteracoes'] ?? $aposta->aceitar_alteracoes->value),
                'oferta_exibida' => $token['oferta'],
                'valor_pedido' => $valor_pedido,
                'margem' => $token['margem'],
                // até a decisão, guarda o que o cliente viu (placar e cotações do token)
                'palpites' => $token['palpites'],
                'ip' => $ip,
                'user_agent' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
            ]);

            if ($token['ao_vivo']) {
                DecidirEncerramento::dispatch($encerramento->id)
                    ->onQueue(DecidirEncerramento::FILA)
                    ->delay(now()->addSeconds($aposta->delay_ao_vivo()))
                    ->afterCommit();
            }

            return $encerramento;
        });

        if (! $token['ao_vivo']) {
            $this->decidir($encerramento->id);
            $encerramento->refresh();
        }

        return [$encerramento, false];
    }

    /**
     * Decide um encerramento Em análise (R-09): refaz a elegibilidade e a oferta com a aposta
     * travada; lance (placar ou período diferente do que o cliente viu) recusa; a oferta nova é
     * comparada com a aceita pela preferência de aceitar alterações.
     */
    public function decidir(int $apostas_encerramentos_id): void
    {
        $codigo = DB::transaction(function () use ($apostas_encerramentos_id) {
            $encerramento = ApostasEncerramentos::lockForUpdate()->find($apostas_encerramentos_id);

            if ($encerramento === null || $encerramento->situacao !== SituacaoEncerramento::EmAnálise) {
                return null;
            }

            $aposta = Apostas::lockForUpdate()->findOrFail($encerramento->apostas_id);
            $oferta = $this->calculo->oferta($aposta);
            $motivo = $this->motivo_recusa($encerramento, $aposta, $oferta);

            $motivo === null
                ? $this->aplicar($encerramento, $aposta, $oferta)
                : $this->recusar($encerramento, $motivo);

            return $aposta->codigo;
        });

        if ($codigo !== null) {
            ApostaAtualizada::emitir($codigo, 'encerramento');
        }
    }

    /**
     * Encerramento automático (R-12): a oferta atual alcançou o valor-alvo; a aposta é encerrada
     * inteira pelo valor da oferta daquele momento, sem tempo de confirmação (o próprio sistema leu
     * os dados atuais). Nulo quando não foi executado (inativo, sem oferta ou abaixo do alvo).
     */
    public function executar_automatico(int $apostas_encerramentos_automaticos_id): ?ApostasEncerramentos
    {
        $encerramento = DB::transaction(function () use ($apostas_encerramentos_automaticos_id) {
            $automatico = ApostasEncerramentosAutomaticos::find($apostas_encerramentos_automaticos_id);

            if ($automatico === null) {
                return null;
            }

            // ordem de bloqueio: aposta → automático
            $aposta = Apostas::lockForUpdate()->findOrFail($automatico->apostas_id);
            $automatico = ApostasEncerramentosAutomaticos::lockForUpdate()->find($automatico->id);

            if ($automatico->situacao !== SituacaoEncerramentoAutomatico::Ativo) {
                return null;
            }

            $oferta = $this->calculo->oferta($aposta);

            if (! $oferta['disponivel'] || bccomp($oferta['oferta'], (string) $automatico->valor_alvo, 2) < 0) {
                return null;
            }

            $encerramento = ApostasEncerramentos::create([
                ...$this->valores_antes($aposta),
                'apostas_id' => $aposta->id,
                'clientes_id' => $aposta->clientes_id,
                'usuarios_id' => null,
                'apostas_encerramentos_automaticos_id' => $automatico->id,
                'modalidade' => ModalidadeEncerramento::Automático,
                'situacao' => SituacaoEncerramento::EmAnálise,
                'chave_idempotencia' => (string) Str::uuid(),
                'aceitar_alteracoes' => AceitarAlteracoes::Qualquer,
                'oferta_exibida' => $oferta['oferta'],
                'valor_pedido' => $oferta['oferta'],
                'margem' => $oferta['margem'],
                'palpites' => $oferta['fotografia'],
            ]);

            $this->aplicar($encerramento, $aposta, $oferta);

            $automatico->forceFill([
                'situacao' => SituacaoEncerramentoAutomatico::Executado,
                'executado_em' => now(),
            ])->save();

            return $encerramento;
        });

        if ($encerramento !== null) {
            ApostaAtualizada::emitir($encerramento->aposta->codigo, 'automatico');
            $this->avisar_automatico($encerramento);
        }

        return $encerramento;
    }

    /**
     * Aviso push do encerramento automático executado (FR-044), com o valor e o horário do crédito.
     */
    private function avisar_automatico(ApostasEncerramentos $encerramento): void
    {
        $codigo = $encerramento->aposta->codigo;
        $credito = $encerramento->creditar_em->copy()->setTimezone(FusoSistema::FUSO)->format('H:i');

        $this->notificacoes->avisar(
            $encerramento->clientes_id,
            TipoNotificacao::EncerramentoAutomático,
            "encerramento:{$encerramento->id}",
            "Aposta {$codigo} encerrada",
            RegrasAposta::reais((string) $encerramento->valor)." entram no seu saldo às {$credito}",
            "/bilhete/{$codigo}",
            apostas_id: $encerramento->apostas_id,
        );
    }

    /**
     * Desfaz um encerramento que vale (FR-033): sem crédito se ainda Registrado; estorno se já
     * Creditado (pode negativar, como o estorno de prêmio da spec 009); o rollover e a fração voltam
     * e, no total, o resultado volta a ser decidido pelos palpites. Deve rodar dentro da transação
     * de quem chama, com a aposta travada.
     */
    public function desfazer(ApostasEncerramentos $encerramento, Apostas $aposta, OrigemApuracao $origem, string $motivo): void
    {
        if (! $encerramento->situacao->vale()) {
            return;
        }

        $antes = ['situacao' => $encerramento->situacao->value, 'fracao_restante' => (string) $aposta->fracao_restante, 'resultado' => $aposta->resultado->value];
        $estorno = null;

        if ($encerramento->situacao === SituacaoEncerramento::Creditado) {
            $estorno = $this->saldo->debitar(
                Clientes::withTrashed()->findOrFail($aposta->clientes_id),
                Carteira::Saldo,
                (string) $encerramento->valor,
                OrigemTransacao::Estorno,
                null,
                $aposta->id,
                "Encerramento desfeito da aposta {$aposta->codigo}",
                permitir_negativo: true,
            );
        }

        $this->rollover->devolver($encerramento->rollovers ?? [], $aposta->id);

        // devolve só a parte que este encerramento tirou (vale para qualquer ordem de desfazimento)
        $aposta->fracao_restante = bcadd((string) $aposta->fracao_restante, bcsub((string) $encerramento->fracao_antes, (string) $encerramento->fracao_depois, self::ESCALA), self::ESCALA);

        if ($aposta->resultado === ResultadoAposta::Encerrada) {
            $aposta->resultado = ResultadoAposta::Aguardando;
        }

        $aposta->save();

        $encerramento->forceFill([
            'situacao' => SituacaoEncerramento::Desfeito,
            'desfeito_em' => now(),
            'motivo_desfeito' => mb_substr($motivo, 0, 255),
            'estorno_clientes_transacoes_id' => $estorno?->id,
        ])->save();

        $this->registro->registrar(
            TipoOperacaoApuracao::EncerramentoDesfeito,
            $origem,
            ['apostas_id' => $aposta->id, 'clientes_id' => $aposta->clientes_id],
            $antes,
            ['situacao' => SituacaoEncerramento::Desfeito->value, 'fracao_restante' => (string) $aposta->fracao_restante, 'resultado' => $aposta->resultado->value],
            $motivo,
            (string) $encerramento->valor,
            Carteira::Saldo,
            $estorno?->id,
        );
    }

    /**
     * Valor pedido dentro da faixa: no total, a oferta do token; no parcial, entre o mínimo e menos
     * que a oferta, com o parcial disponível.
     *
     * @param  array<string, mixed>  $token
     *
     * @throws RegraApostaException
     */
    private function conferir_valor(Apostas $aposta, ModalidadeEncerramento $modalidade, string $valor, array $token): void
    {
        if ($modalidade === ModalidadeEncerramento::Total) {
            if (bccomp($valor, (string) $token['oferta'], 2) !== 0) {
                throw new OfertaAlteradaException;
            }

            return;
        }

        $parcial = $this->calculo->parcial($aposta);

        if (! $parcial['disponivel']) {
            throw new RegraApostaException($parcial['motivo']);
        }

        if (bccomp($valor, (string) $token['minimo'], 2) < 0 || bccomp($valor, (string) $token['oferta'], 2) >= 0) {
            throw new RegraApostaException('O valor do encerramento parcial deve ser de '.RegrasAposta::reais((string) $token['minimo'])
                .' a menos de '.RegrasAposta::reais((string) $token['oferta']).'.');
        }
    }

    /**
     * Motivo para recusar o pedido na decisão, ou nulo para aceitar.
     *
     * @param  array<string, mixed>  $oferta
     */
    private function motivo_recusa(ApostasEncerramentos $encerramento, Apostas $aposta, array $oferta): ?string
    {
        if (! $oferta['disponivel']) {
            return $oferta['motivo'] ?? 'Esta aposta não pode mais ser encerrada.';
        }

        if (bccomp((string) $aposta->fracao_restante, (string) $encerramento->fracao_antes, self::ESCALA) !== 0) {
            return 'A oferta mudou.';
        }

        $vistos = collect($encerramento->palpites)->keyBy('id');
        $atuais = collect($oferta['fotografia'])->keyBy('id');

        // um palpite decidido ou um placar/período diferente do que o cliente viu é lance
        if ($vistos->keys()->sort()->values()->all() !== $atuais->keys()->sort()->values()->all()) {
            return 'Houve lance durante a confirmação.';
        }

        foreach ($atuais as $id => $atual) {
            $visto = $vistos[$id];

            if ($visto['placar_casa'] !== $atual['placar_casa'] || $visto['placar_fora'] !== $atual['placar_fora'] || $visto['periodo'] !== $atual['periodo']) {
                return "Houve lance em {$atual['jogo']} durante a confirmação.";
            }
        }

        if (! $encerramento->aceitar_alteracoes->aceita((string) $encerramento->oferta_exibida, $oferta['oferta'])) {
            return 'A oferta mudou.';
        }

        if ($encerramento->modalidade === ModalidadeEncerramento::Parcial
            && (bccomp((string) $encerramento->valor_pedido, $oferta['oferta'], 2) >= 0 || bccomp((string) $encerramento->valor_pedido, $oferta['minimo'], 2) < 0)) {
            return 'A oferta mudou.';
        }

        return null;
    }

    /**
     * Efeitos do encerramento aceito (R-10): resultado Encerrada ou fração reduzida, rollover
     * retirado, automático cancelado e o registro com os valores antes e depois.
     *
     * @param  array<string, mixed>  $oferta
     */
    private function aplicar(ApostasEncerramentos $encerramento, Apostas $aposta, array $oferta): void
    {
        $total = $encerramento->modalidade !== ModalidadeEncerramento::Parcial;
        $valor = $total ? $oferta['oferta'] : (string) $encerramento->valor_pedido;
        $proporcao = $total ? '1' : bcdiv($valor, $oferta['oferta'], self::ESCALA);
        $fracao_antes = (string) $aposta->fracao_restante;
        $fracao_depois = $total ? '0' : bcmul($fracao_antes, bcsub('1', $proporcao, self::ESCALA), self::ESCALA);

        $rollovers = $this->rollover->retirar($aposta, $proporcao);

        $aposta->fracao_restante = $fracao_depois;

        if ($total) {
            $aposta->resultado = ResultadoAposta::Encerrada;
        }

        $aposta->save();

        // encerramento manual cancela o automático (FR-031)
        if ($encerramento->modalidade !== ModalidadeEncerramento::Automático) {
            ApostasEncerramentosAutomaticos::cancelar_ativo($aposta->id, 'Encerramento manual');
        }

        $encerramento->forceFill([
            'situacao' => SituacaoEncerramento::Registrado,
            'oferta' => $oferta['oferta'],
            'valor' => $valor,
            'margem' => $oferta['margem'],
            'proporcao' => $proporcao,
            'fracao_depois' => $fracao_depois,
            'valor_apostado_depois' => $aposta->valor_em_jogo(),
            'premio_depois' => $aposta->total_a_pagar(),
            'palpites' => $oferta['fotografia'],
            'rollovers' => $rollovers,
            'decidido_em' => now(),
            'creditar_em' => now()->addMinutes($this->configuracoes->minutos_janela()),
        ])->save();
    }

    private function recusar(ApostasEncerramentos $encerramento, string $motivo): void
    {
        $encerramento->forceFill([
            'situacao' => SituacaoEncerramento::Recusado,
            'motivo_recusa' => mb_substr($motivo, 0, 255),
            'decidido_em' => now(),
        ])->save();
    }

    /**
     * Valores da aposta antes do encerramento, gravados no registro (FR-035).
     *
     * @return array<string, string>
     */
    private function valores_antes(Apostas $aposta): array
    {
        return [
            'fracao_antes' => (string) $aposta->fracao_restante,
            'valor_apostado_antes' => $aposta->valor_em_jogo(),
            'premio_antes' => $aposta->total_a_pagar(),
        ];
    }
}
