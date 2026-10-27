<?php

namespace App\Http\Controllers;

use App\Enums\AlvoRegra;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfrontosRequest;
use App\Http\Requests\FiltrosConfrontosPainelRequest;
use App\Http\Resources\ConfrontosResource;
use App\Models\ApostasPalpites;
use App\Models\Confrontos;
use App\Models\ConfrontosNaoPermitidos;
use App\Models\OperacoesApuracao;
use App\Services\CalculoCotacoes;
use App\Services\ExposicaoConfrontos;
use App\Services\ListagemConfrontosPainel;
use App\Services\Publico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;

/**
 * Confrontos do pré-jogo no painel: listagem, ativar e desativar e cadastro manual; a lista, os
 * cartões e a gaveta da tela Confrontos (spec 023).
 */
class ConfrontosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    // cotações principais mostradas na gaveta: Casa, Empate e Fora
    private const CODIGOS_PRINCIPAIS = ['odd1', 'odd2', 'odd3'];

    public function __construct(private ListagemConfrontosPainel $listagem) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('confrontos.listar', only: ['index', 'show', 'resumo', 'esportes']),
            self::permissao_cliente('confrontos.cadastrar', only: ['store']),
            self::permissao_cliente('confrontos.editar', only: ['update']),
            self::permissao_cliente('confrontos.excluir', only: ['destroy']),
            self::permissao_cliente('confrontos.alterar_situacao', only: ['alterar_situacao']),
        ];
    }

    /**
     * Lista da tela Confrontos (spec 023): filtros, dia, tipo e os campos da linha.
     */
    public function index(FiltrosConfrontosPainelRequest $request): AnonymousResourceCollection
    {
        $confrontos = $this->listagem->com_detalhes($this->listagem->consulta($request->filtros()), $request->user())
            ->paginate($request->por_pagina())
            ->withQueryString();

        return ConfrontosResource::collection($confrontos);
    }

    /**
     * Cartões da tela Confrontos (spec 023, contracts/api §2.1).
     */
    public function resumo(FiltrosConfrontosPainelRequest $request, ExposicaoConfrontos $exposicao): JsonResponse
    {
        return response()->json(['data' => $this->listagem->resumo($request->user(), $request->filtros(), $exposicao)]);
    }

    /**
     * Esportes já usados pelos jogos, para o cadastro manual (spec 023, contracts/api §2.7).
     */
    public function esportes(): JsonResponse
    {
        return response()->json(['data' => Confrontos::query()->distinct()->orderBy('esporte')->pluck('esporte')]);
    }

    /**
     * Gaveta do jogo (spec 023, contracts/api §1.2): resultado completo com a origem, cotações
     * principais como o visitante do site vê e se há não permitido para todos.
     */
    public function show(Request $request, Confrontos $confronto, CalculoCotacoes $calculo): ConfrontosResource
    {
        $detalhe = $this->listagem->com_detalhes(Confrontos::query()->whereKey($confronto->id), $request->user())
            ->with(['jogadores', 'gols'])
            ->addSelect([
                'origem_resultado' => OperacoesApuracao::query()->select('origem')
                    ->whereColumn('confrontos_id', 'confrontos.id')->latest('id')->limit(1),
                'nao_permitido_todos' => ConfrontosNaoPermitidos::query()->selectRaw('COUNT(*) > 0')
                    ->whereColumn('confrontos_id', 'confrontos.id')->where('alvo', AlvoRegra::Todos),
            ])
            ->firstOrFail();

        $ajustadas = $calculo->ajustar(Publico::visitante(), CalculoCotacoes::PRE_JOGO, collect([(object) [
            'id' => $detalhe->id,
            'campeonatos_id' => $detalhe->campeonatos_id,
            'confrontos_id' => $detalhe->id,
            'cotacoes' => $detalhe->cotacoes ?? [],
        ]]), self::CODIGOS_PRINCIPAIS)[$detalhe->id] ?? [];

        // código que o jogo não tem fica null (a base do provedor é zero)
        $detalhe->setAttribute('cotacoes_site', collect(self::CODIGOS_PRINCIPAIS)->mapWithKeys(fn (string $codigo) => [
            $codigo => isset(($detalhe->cotacoes ?? [])[$codigo]) ? number_format($ajustadas[$codigo] ?? 0, 2, '.', '') : null,
        ])->all());

        return new ConfrontosResource($detalhe);
    }

    /**
     * Confronto manual: em campeonato manual, ativo, aguardando e com a quantidade de cotações
     * calculada.
     */
    public function store(ConfrontosRequest $request): ConfrontosResource
    {
        $confronto = new Confrontos($this->dados_confronto($request));
        $confronto->situacao = SituacaoConfronto::Aguardando;
        $confronto->forceFill(['manual' => true, 'ativo' => true])->save();

        return new ConfrontosResource($confronto->refresh()->load('campeonato'));
    }

    public function update(ConfrontosRequest $request, Confrontos $confronto): ConfrontosResource
    {
        $this->garantir_manual($confronto);

        $confronto->fill($this->dados_confronto($request));

        if ($request->filled('situacao')) {
            $confronto->situacao = SituacaoConfronto::from($request->validated('situacao'));
        }

        $confronto->save();

        return new ConfrontosResource($confronto->load('campeonato'));
    }

    /**
     * Exclui o jogo manual; recusa enquanto houver apostas vivas nele (spec 023, R-16).
     */
    public function destroy(Confrontos $confronto): Response
    {
        $this->garantir_manual($confronto);

        $apostas = self::apostas_vivas($confronto->id);

        if ($apostas > 0) {
            throw ValidationException::withMessages(['confronto' => $apostas === 1
                ? 'Este jogo tem 1 aposta. Cancele o jogo em vez de excluir.'
                : "Este jogo tem {$apostas} apostas. Cancele o jogo em vez de excluir."]);
        }

        $confronto->delete();

        return response()->noContent();
    }

    /**
     * Ativa ou desativa para o sistema inteiro; desativado, some também do ao vivo (só Admin e
     * Supervisor).
     */
    public function alterar_situacao(Request $request, Confrontos $confronto): ConfrontosResource
    {
        $dados = $request->validate(
            ['ativo' => ['required', 'boolean']],
            ['ativo.required' => 'O campo ativo é obrigatório.', 'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.'],
        );

        $confronto->forceFill(['ativo' => (bool) $dados['ativo']])->save();

        return new ConfrontosResource($confronto);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados_confronto(ConfrontosRequest $request): array
    {
        $cotacoes = $request->cotacoes();

        return [
            'campeonatos_id' => $request->validated('campeonatos_id'),
            'time_casa' => $request->validated('time_casa'),
            'escudo_casa' => $request->validated('escudo_casa'),
            'time_fora' => $request->validated('time_fora'),
            'escudo_fora' => $request->validated('escudo_fora'),
            'esporte' => mb_strtoupper($request->validated('esporte')),
            'data_inicio' => $request->data_inicio_utc(),
            'cotacoes' => $cotacoes,
            'quantidade_cotacoes' => count($cotacoes),
        ];
    }

    /**
     * Quantidade de apostas vivas (Ativa, Pendente ou Em análise) com palpite ativo nos jogos
     * informados; Canceladas, Recusadas e Expiradas não contam (spec 023, R-16).
     *
     * @param  int|list<int>  $confrontos_id
     */
    public static function apostas_vivas(int|array $confrontos_id): int
    {
        return ApostasPalpites::query()
            ->join('apostas', 'apostas.id', '=', 'apostas_palpites.apostas_id')
            ->whereNull('apostas.deleted_at')
            ->whereIn('apostas_palpites.confrontos_id', (array) $confrontos_id)
            ->where('apostas_palpites.situacao', SituacaoPalpite::Ativo->value)
            ->whereIn('apostas.situacao', [SituacaoAposta::Ativa->value, SituacaoAposta::Pendente->value, SituacaoAposta::EmAnálise->value])
            ->distinct()
            ->count('apostas.id');
    }

    private function garantir_manual(Confrontos $confronto): void
    {
        if (! $confronto->manual) {
            throw ValidationException::withMessages(['confronto' => 'Só registros manuais podem ser alterados.']);
        }
    }
}
