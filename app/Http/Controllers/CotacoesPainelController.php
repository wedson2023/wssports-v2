<?php

namespace App\Http\Controllers;

use App\Enums\AlvoRegra;
use App\Enums\Funcao;
use App\Enums\SituacaoConfronto;
use App\Http\Requests\CadeadosCotacoesRequest;
use App\Models\Campeonatos;
use App\Models\Clientes;
use App\Models\Confrontos;
use App\Models\PorcentagensCadeados;
use App\Models\PorcentagensCampeonatos;
use App\Models\PorcentagensConfrontos;
use App\Models\PorcentagensHistoricos;
use App\Models\PorcentagensVendedores;
use App\Models\PorcentagensVendedoresAoVivo;
use App\Models\Usuarios;
use App\Services\AlcanceClientes;
use App\Services\AlcanceHierarquia;
use App\Services\CadeadosCotacoes;
use App\Services\CalculoCotacoes;
use App\Services\Publico;
use App\Support\CodigosCotacao;
use App\Support\FusoSistema;
use App\Support\NomesCotacoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Consultas da tela "Cotações" do painel (spec 016): nomes, públicos, porcentagens de um usuário,
 * listas com o resumo das regras, prévia, Regras ativas, histórico e cadeados. A gravação das
 * regras continua nas rotas da spec 003.
 */
class CotacoesPainelController extends Controller
{
    /**
     * Permissão de cada camada da tela.
     */
    private const PERMISSOES = [
        'vendedores' => 'porcentagens_vendedores.editar',
        'clientes' => 'porcentagens_clientes.editar',
        'campeonatos' => 'porcentagens_campeonatos.editar',
        'confrontos' => 'porcentagens_confrontos.editar',
        'tetos' => 'confrontos_teto_cotacoes.editar',
    ];

    private const SEM_PERMISSAO = 'Você não tem permissão para esta ação.';

    public function __construct(private AlcanceHierarquia $alcance) {}

    /**
     * Nome legível de cada código de cotação (323 códigos e jogador).
     */
    public function nomes(Request $request): JsonResponse
    {
        $this->exigir($request, ...array_values(self::PERMISSOES));

        $nomes = collect(CodigosCotacao::regras())->mapWithKeys(fn (string $codigo) => [$codigo => NomesCotacoes::nome($codigo)]);

        return response()->json(['nomes' => $nomes]);
    }

    /**
     * Usuários cujas porcentagens o solicitante pode editar: ele e a rede (Admin e Supervisor: todos).
     */
    public function publicos(Request $request): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['vendedores']);
        $filtros = $this->validar_lista($request);
        $donos = $this->alcance->ids_donos_visiveis($request->user());

        $usuarios = Usuarios::with(['roles:id,name', 'superior:id,nome'])
            ->where('ativo', true)
            ->when($donos !== null, fn (Builder $c) => $c->whereIn('id', $donos))
            ->when(isset($filtros['busca']), fn (Builder $c) => $c->where(fn (Builder $b) => $b
                ->where('nome', 'like', $this->termo($filtros['busca']))
                ->orWhere('login', 'like', $this->termo($filtros['busca']))))
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 50)
            ->withQueryString();

        return response()->json($usuarios->through(fn (Usuarios $usuario) => [
            'id' => $usuario->id,
            'nome' => $usuario->nome,
            'login' => $usuario->login,
            'funcao' => $usuario->funcao()?->value,
            'superior' => $usuario->superior ? ['id' => $usuario->superior->id, 'nome' => $usuario->superior->nome] : null,
        ]));
    }

    /**
     * Busca de clientes para a regra de um cliente específico.
     */
    public function clientes(Request $request): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['clientes']);
        $filtros = $this->validar_lista($request);

        $clientes = Clientes::query()
            ->when(isset($filtros['busca']), fn (Builder $c) => $c->where(fn (Builder $b) => $b
                ->where('nome', 'like', $this->termo($filtros['busca']))
                ->orWhere('telefone', 'like', $this->termo($filtros['busca']))
                ->orWhere('email', 'like', $this->termo($filtros['busca']))))
            // só os clientes da rede (spec 019, FR-003a)
            ->tap(fn (Builder $c) => app(AlcanceClientes::class)->aplicar($c, $request->user()))
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 20, ['id', 'nome', 'telefone', 'email'])
            ->withQueryString();

        return response()->json($clientes);
    }

    /**
     * Porcentagens de um usuário, a soma dos superiores e os cadeados que valem para ele.
     */
    public function usuario(Request $request, Usuarios $usuario, CadeadosCotacoes $cadeados): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['vendedores']);
        $this->alcance->garantir_dono($request->user(), $usuario->id);
        $filtros = $request->validate([
            'campeonatos_id' => ['nullable', 'integer', 'exists:campeonatos,id'],
        ], [
            'campeonatos_id.exists' => 'Campeonato não encontrado.',
        ]);

        $acima = array_values(array_diff($usuario->ids_hierarquia_acima(), [$usuario->id]));
        $soma = fn (string $model) => $this->somar($model::whereIn('usuarios_id', $acima)->pluck('valores'));

        return response()->json([
            'usuarios_id' => $usuario->id,
            'pre_jogo' => (object) (PorcentagensVendedores::where('usuarios_id', $usuario->id)->value('valores') ?? []),
            'ao_vivo' => (object) (PorcentagensVendedoresAoVivo::where('usuarios_id', $usuario->id)->value('valores') ?? []),
            'superiores' => [
                'pre_jogo' => (object) $soma(PorcentagensVendedores::class),
                'ao_vivo' => (object) $soma(PorcentagensVendedoresAoVivo::class),
            ],
            'cadeados' => $cadeados->para_tela($request->user(), PorcentagensCadeados::VENDEDORES, $usuario),
            // cadeados da regra de campeonato com alvo Vendedores deste usuário
            'cadeados_campeonato' => isset($filtros['campeonatos_id'])
                ? $cadeados->para_tela($request->user(), PorcentagensCadeados::CAMPEONATOS, $usuario, (int) $filtros['campeonatos_id'])
                : [],
        ]);
    }

    /**
     * Partes do cálculo de um jogo para a prévia, com a regra em edição fora das parcelas.
     */
    public function previa(Request $request, CalculoCotacoes $calculo): JsonResponse
    {
        $this->exigir($request, ...array_values(self::PERMISSOES));
        $filtros = $request->validate([
            'confrontos_id' => ['nullable', 'integer', 'exists:confrontos,id'],
            'campeonatos_id' => ['nullable', 'integer'],
            'publico' => ['required', 'in:clientes,cliente,usuario'],
            'publico_id' => ['required_unless:publico,clientes', 'nullable', 'integer'],
            'tipo' => ['required', 'in:pre_jogo,ao_vivo'],
            'camada' => ['nullable', 'in:campeonatos,confrontos'],
            'alvo' => ['nullable', Rule::enum(AlvoRegra::class)],
            'alvo_usuarios_id' => ['nullable', 'integer'],
        ], [
            'confrontos_id.exists' => 'Jogo não encontrado.',
            'publico.in' => 'O público deve ser clientes, cliente ou usuario.',
            'publico_id.required_unless' => 'Informe o cliente ou o usuário.',
            'tipo.in' => 'O tipo deve ser pre_jogo ou ao_vivo.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
        ]);

        $confronto = isset($filtros['confrontos_id'])
            ? Confrontos::with('campeonato:id,nome')->find($filtros['confrontos_id'])
            : $this->proximos_jogos()
                ->when(isset($filtros['campeonatos_id']), fn (Builder $c) => $c->where('campeonatos_id', $filtros['campeonatos_id']))
                ->first();

        if ($confronto === null) {
            return response()->json(['confronto' => null, 'partes' => (object) []]);
        }

        $excluir = isset($filtros['camada'], $filtros['alvo'])
            ? ['camada' => $filtros['camada'], 'alvo' => AlvoRegra::from($filtros['alvo']), 'usuarios_id' => $filtros['alvo_usuarios_id'] ?? null]
            : [];
        $item = (object) ['campeonatos_id' => $confronto->campeonatos_id, 'confrontos_id' => $confronto->id, 'cotacoes' => $confronto->cotacoes ?? []];

        return response()->json([
            'confronto' => $this->resumo_confronto($confronto),
            'partes' => (object) $calculo->detalhar($this->publico_da_previa($request, $filtros), $filtros['tipo'], $item, CodigosCotacao::regras(), $excluir),
        ]);
    }

    /**
     * Próximos 20 jogos do pré-jogo para o "Trocar jogo" da prévia.
     */
    public function previa_jogos(Request $request): JsonResponse
    {
        $this->exigir($request, ...array_values(self::PERMISSOES));
        $filtros = $this->validar_lista($request);

        $jogos = $this->proximos_jogos()
            ->when(isset($filtros['busca']), fn (Builder $c) => $this->buscar_time($c, $filtros['busca']))
            ->limit(20)
            ->get();

        return response()->json(['data' => $jogos->map(fn (Confrontos $confronto) => $this->resumo_confronto($confronto))]);
    }

    /**
     * Tranca códigos para um usuário da rede (Admin e Supervisor).
     */
    public function trancar(CadeadosCotacoesRequest $request, CadeadosCotacoes $cadeados): JsonResponse
    {
        [$camada, $usuario, $tipo, $campeonatos_id] = $this->dados_cadeado($request);

        return response()->json([
            'cadeados' => $cadeados->trancar($request->user(), $usuario, $camada, $tipo, $campeonatos_id, $request->validated('codigos')),
            'nao_destrancados' => 0,
        ]);
    }

    /**
     * Destranca os códigos que o solicitante pode destrancar; os demais são informados.
     */
    public function destrancar(CadeadosCotacoesRequest $request, CadeadosCotacoes $cadeados): JsonResponse
    {
        [$camada, $usuario, $tipo, $campeonatos_id] = $this->dados_cadeado($request);

        return response()->json($cadeados->destrancar($request->user(), $usuario, $camada, $tipo, $campeonatos_id, $request->validated('codigos')));
    }

    /**
     * Países dos campeonatos, com a bandeira, para o filtro.
     */
    public function paises(Request $request): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['campeonatos']);

        $paises = Campeonatos::query()
            ->selectRaw('pais, MAX(bandeira) as bandeira')
            ->groupBy('pais')
            ->orderBy('pais')
            ->get();

        return response()->json(['paises' => $paises]);
    }

    /**
     * Campeonatos com os jogos futuros e o resumo das regras visíveis ao solicitante.
     */
    public function campeonatos(Request $request): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['campeonatos']);
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'pais' => ['nullable', 'string', 'max:100'],
            'com_ajuste' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], $this->mensagens_lista());

        $campeonatos = Campeonatos::query()
            ->withCount(['confrontos as jogos_futuros' => fn (Builder $c) => $c
                ->where('situacao', SituacaoConfronto::Aguardando)
                ->where('data_inicio', '>', now())])
            ->when(isset($filtros['busca']), fn (Builder $c) => $c->where('nome', 'like', $this->termo($filtros['busca'])))
            ->when(isset($filtros['pais']), fn (Builder $c) => $c->where('pais', $filtros['pais']))
            ->when(! empty($filtros['com_ajuste']), fn (Builder $c) => $c->whereHas('porcentagens', fn (Builder $r) => $this->regras_visiveis($request, $r)))
            ->orderByDesc('favorito')
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        $resumos = $this->resumos($request, PorcentagensCampeonatos::query(), 'campeonatos_id', $campeonatos->pluck('id'));

        return response()->json($campeonatos->through(fn (Campeonatos $campeonato) => [
            'id' => $campeonato->id,
            'nome' => $campeonato->nome,
            'pais' => $campeonato->pais,
            'bandeira' => $campeonato->bandeira,
            'favorito' => (bool) $campeonato->favorito,
            'jogos_futuros' => $campeonato->jogos_futuros,
            'resumo' => $resumos[$campeonato->id] ?? null,
        ]));
    }

    /**
     * Jogos do pré-jogo de hoje a depois de amanhã com o resumo das regras.
     */
    public function confrontos(Request $request): JsonResponse
    {
        $this->exigir($request, self::PERMISSOES['confrontos']);
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'campeonatos_id' => ['nullable', 'integer'],
            'dia' => ['nullable', 'in:hoje,amanha,depois_de_amanha'],
            'com_ajuste' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [...$this->mensagens_lista(), 'dia.in' => 'O dia deve ser hoje, amanha ou depois_de_amanha.']);

        $confrontos = $this->proximos_jogos()
            ->when(isset($filtros['dia']), fn (Builder $c) => $c->whereBetween('data_inicio', $this->dia_em_utc($filtros['dia'])))
            ->when(isset($filtros['busca']), fn (Builder $c) => $this->buscar_time($c, $filtros['busca']))
            ->when(isset($filtros['campeonatos_id']), fn (Builder $c) => $c->where('campeonatos_id', $filtros['campeonatos_id']))
            ->when(! empty($filtros['com_ajuste']), fn (Builder $c) => $c->whereExists(fn (QueryBuilder $r) => $this->regras_visiveis(
                $request,
                $r->from('porcentagens_confrontos')->whereColumn('porcentagens_confrontos.confrontos_id', 'confrontos.id'),
            )))
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        $resumos = $this->resumos($request, PorcentagensConfrontos::query(), 'confrontos_id', $confrontos->pluck('id'));

        return response()->json($confrontos->through(fn (Confrontos $confronto) => [
            ...$this->resumo_confronto($confronto),
            'resumo' => $resumos[$confronto->id] ?? null,
        ]));
    }

    /**
     * Todas as regras com algum código ajustado que o solicitante pode ver, numa lista paginada.
     */
    public function regras_ativas(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'camada' => ['nullable', Rule::in(array_keys(self::PERMISSOES))],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [...$this->mensagens_lista(), 'camada.in' => 'A camada informada é inválida.']);

        $camadas = collect(self::PERMISSOES)
            ->filter(fn (string $permissao) => Funcao::usuario_pode($request->user(), $permissao))
            ->keys()
            ->when(isset($filtros['camada']), fn (Collection $c) => $c->intersect([$filtros['camada']]));

        abort_if($camadas->isEmpty() && ! isset($filtros['camada']), 403, self::SEM_PERMISSAO);

        $consultas = $camadas->flatMap(fn (string $camada) => $this->consultas_regras($request, $camada))->values();

        if ($consultas->isEmpty()) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
        }

        $uniao = $consultas->slice(1)->reduce(fn (QueryBuilder $todas, QueryBuilder $consulta) => $todas->unionAll($consulta), $consultas->first());
        $pagina = DB::query()->fromSub($uniao, 'regras')
            ->orderByDesc('atualizado_em')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        $nomes = $this->nomes_das_referencias(collect($pagina->items()));

        return response()->json($pagina->through(function (object $regra) use ($nomes) {
            $valores = array_map('floatval', json_decode($regra->valores, true) ?: []);

            return [
                'camada' => $regra->camada,
                'referencia' => ['id' => $regra->referencia_id, 'nome' => $nomes[$regra->camada][$regra->referencia_id] ?? null],
                'alvo' => $regra->alvo,
                'usuario' => $regra->usuarios_id ? $nomes['usuarios'][$regra->usuarios_id] ?? null : null,
                'tipo' => $regra->tipo,
                'quantidade' => count($valores),
                'minimo' => min($valores),
                'maximo' => max($valores),
                'atualizado_em' => $regra->atualizado_em,
            ];
        }));
    }

    /**
     * Histórico de uma regra, da alteração mais nova para a mais antiga.
     */
    public function historico(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'camada' => ['required', Rule::in(array_keys(self::PERMISSOES))],
            'usuarios_id' => ['nullable', 'integer'],
            'clientes_id' => ['nullable', 'integer'],
            'campeonatos_id' => ['nullable', 'integer'],
            'confrontos_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'in:pre_jogo,ao_vivo'],
            'alvo' => ['nullable', Rule::enum(AlvoRegra::class)],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            ...$this->mensagens_lista(),
            'camada.required' => 'A camada é obrigatória.',
            'camada.in' => 'A camada informada é inválida.',
            'tipo.in' => 'O tipo deve ser pre_jogo ou ao_vivo.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
        ]);

        $this->exigir($request, self::PERMISSOES[$filtros['camada']]);

        // alcance: regra de usuário só da rede; Gerente só vê alvo Vendedores da rede
        if (isset($filtros['usuarios_id'])) {
            $this->alcance->garantir_dono($request->user(), (int) $filtros['usuarios_id']);
        }

        $donos = $this->alcance->ids_donos_visiveis($request->user());

        $historico = PorcentagensHistoricos::with('autor:id,nome')
            ->where('camada', $filtros['camada'])
            ->when(array_key_exists('usuarios_id', $filtros), fn (Builder $c) => $c->where('alvo_usuarios_id', $filtros['usuarios_id']))
            ->when($filtros['camada'] === 'clientes', fn (Builder $c) => isset($filtros['clientes_id'])
                ? $c->where('clientes_id', $filtros['clientes_id'])
                : $c->whereNull('clientes_id'))
            ->when(isset($filtros['campeonatos_id']), fn (Builder $c) => $c->where('campeonatos_id', $filtros['campeonatos_id']))
            ->when(isset($filtros['confrontos_id']), fn (Builder $c) => $c->where('confrontos_id', $filtros['confrontos_id']))
            ->when(isset($filtros['tipo']), fn (Builder $c) => $c->where('tipo', $filtros['tipo']))
            ->when(isset($filtros['alvo']), fn (Builder $c) => $c->where('alvo', $filtros['alvo']))
            ->when($donos !== null && in_array($filtros['camada'], ['campeonatos', 'confrontos'], true), fn (Builder $c) => $c->whereIn('alvo_usuarios_id', $donos))
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return response()->json($historico->through(fn (PorcentagensHistoricos $linha) => [
            'id' => $linha->id,
            'acao' => $linha->acao,
            'autor' => ['id' => $linha->usuarios_id, 'nome' => $linha->autor?->nome],
            'criado_em' => $linha->created_at?->toIso8601String(),
            'tipo' => $linha->tipo,
            'alvo' => $linha->alvo,
            'alteracoes' => (object) $linha->alteracoes,
        ]));
    }

    /**
     * Exige ao menos uma das permissões (com a regra de uso por função).
     */
    private function exigir(Request $request, string ...$permissoes): void
    {
        foreach ($permissoes as $permissao) {
            if (Funcao::usuario_pode($request->user(), $permissao)) {
                return;
            }
        }

        abort(403, self::SEM_PERMISSAO);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar_lista(Request $request): array
    {
        return $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], $this->mensagens_lista());
    }

    /**
     * @return array<string, string>
     */
    private function mensagens_lista(): array
    {
        return [
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'pais.max' => 'O país deve ter no máximo 100 caracteres.',
            'com_ajuste.boolean' => 'O filtro só com ajuste deve ser verdadeiro ou falso.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    private function termo(string $busca): string
    {
        return '%'.addcslashes($busca, '%_\\').'%';
    }

    private function buscar_time(Builder $consulta, string $busca): Builder
    {
        return $consulta->where(fn (Builder $b) => $b->where('time_casa', 'like', $this->termo($busca))->orWhere('time_fora', 'like', $this->termo($busca)));
    }

    /**
     * Jogos do pré-jogo ainda não iniciados, de agora até o fim de depois de amanhã (Brasília).
     */
    private function proximos_jogos(): Builder
    {
        return Confrontos::with('campeonato:id,nome,pais,bandeira')
            ->where('situacao', SituacaoConfronto::Aguardando)
            ->whereBetween('data_inicio', [now(), FusoSistema::fim_do_dia_em(2)])
            ->orderBy('data_inicio')
            ->orderBy('time_casa');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dia_em_utc(string $dia): array
    {
        $dias = match ($dia) {
            'amanha' => 1,
            'depois_de_amanha' => 2,
            default => 0,
        };

        return [FusoSistema::inicio_do_dia(now()->addDays($dias))->format('Y-m-d H:i:s'), FusoSistema::fim_do_dia_em($dias)->format('Y-m-d H:i:s')];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo_confronto(Confrontos $confronto): array
    {
        return [
            'id' => $confronto->id,
            'time_casa' => $confronto->time_casa,
            'time_fora' => $confronto->time_fora,
            'data_inicio' => $confronto->data_inicio?->toIso8601String(),
            'campeonatos_id' => $confronto->campeonatos_id,
            'campeonato' => $confronto->campeonato
                ? ['id' => $confronto->campeonato->id, 'nome' => $confronto->campeonato->nome, 'pais' => $confronto->campeonato->pais, 'bandeira' => $confronto->campeonato->bandeira]
                : null,
        ];
    }

    /**
     * Público da prévia: clientes do site (visitante), um cliente ou um usuário do painel.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function publico_da_previa(Request $request, array $filtros): Publico
    {
        if ($filtros['publico'] === 'clientes') {
            return Publico::visitante();
        }

        if ($filtros['publico'] === 'cliente') {
            $this->exigir($request, self::PERMISSOES['clientes']);

            return new Publico(Publico::CLIENTE, cliente: Clientes::findOrFail($filtros['publico_id']));
        }

        $usuario = $this->alcance->garantir_dono($request->user(), (int) $filtros['publico_id']);

        return new Publico($usuario->funcao() === Funcao::Vendedor ? Publico::VENDEDOR : Publico::GESTOR, usuario: $usuario);
    }

    /**
     * Camada, usuário (no alcance), tipo e campeonato do pedido de cadeado.
     *
     * @return array{0: string, 1: Usuarios, 2: string|null, 3: int|null}
     */
    private function dados_cadeado(CadeadosCotacoesRequest $request): array
    {
        $camada = $request->validated('camada');
        $this->exigir($request, self::PERMISSOES[$camada]);
        $usuario = $this->alcance->garantir_dono($request->user(), (int) $request->validated('usuarios_id'));

        return [$camada, $usuario, $request->validated('tipo'), $request->validated('campeonatos_id')];
    }

    /**
     * Regras de campeonato ou jogo que o solicitante vê (Gerente: alvo Vendedores da rede), com
     * algum código ajustado.
     */
    private function regras_visiveis(Request $request, Builder|QueryBuilder $consulta): Builder|QueryBuilder
    {
        $donos = $this->alcance->ids_donos_visiveis($request->user());

        return $consulta
            ->whereNull('deleted_at')
            ->whereRaw('JSON_LENGTH(valores) > 0')
            ->when($donos !== null, fn ($c) => $c->where('alvo', AlvoRegra::Vendedores->value)->whereIn('usuarios_id', $donos));
    }

    /**
     * Resumo das regras visíveis por item da página: quantidade, alvos e faixa de valores.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function resumos(Request $request, Builder $consulta, string $coluna, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return $this->regras_visiveis($request, $consulta->whereIn($coluna, $ids->all()))
            ->get([$coluna, 'alvo', 'valores'])
            ->groupBy($coluna)
            ->map(function (Collection $regras) {
                $valores = $regras->flatMap(fn ($regra) => array_map('floatval', array_values($regra->valores ?? [])));

                return [
                    'quantidade' => $regras->count(),
                    'alvos' => $regras->map(fn ($regra) => $regra->alvo instanceof AlvoRegra ? $regra->alvo->value : $regra->alvo)->unique()->values(),
                    'minimo' => $valores->min(),
                    'maximo' => $valores->max(),
                ];
            })
            ->all();
    }

    /**
     * Consultas normalizadas de uma camada para Regras ativas (só regras com algum código).
     *
     * @return list<QueryBuilder>
     */
    private function consultas_regras(Request $request, string $camada): array
    {
        $donos = $this->alcance->ids_donos_visiveis($request->user());
        $base = fn (string $tabela, string $referencia, string $alvo, string $usuario, string $tipo) => DB::table($tabela)
            ->selectRaw("'{$camada}' as camada, {$referencia} as referencia_id, {$alvo} as alvo, {$usuario} as usuarios_id, {$tipo} as tipo, valores, updated_at as atualizado_em")
            ->whereNull('deleted_at')
            ->whereRaw('JSON_LENGTH(valores) > 0');

        return match ($camada) {
            'vendedores' => collect(['porcentagens_vendedores' => 'pre_jogo', 'porcentagens_vendedores_ao_vivo' => 'ao_vivo'])
                ->map(fn (string $tipo, string $tabela) => $base($tabela, 'usuarios_id', 'null', 'usuarios_id', "'{$tipo}'")
                    ->when($donos !== null, fn (QueryBuilder $c) => $c->whereIn('usuarios_id', $donos)))
                ->values()->all(),
            'clientes' => collect(['porcentagens_clientes' => 'pre_jogo', 'porcentagens_clientes_ao_vivo' => 'ao_vivo'])
                ->map(fn (string $tipo, string $tabela) => $base($tabela, 'clientes_id', 'null', 'null', "'{$tipo}'"))
                ->values()->all(),
            'campeonatos', 'confrontos' => [
                $this->regras_visiveis($request, $base("porcentagens_{$camada}", "{$camada}_id", 'alvo', 'usuarios_id', 'null')),
            ],
            'tetos' => [
                DB::table('confrontos_teto_cotacoes')
                    ->selectRaw("'tetos' as camada, null as referencia_id, null as alvo, null as usuarios_id, null as tipo, tetos as valores, updated_at as atualizado_em")
                    ->whereRaw('JSON_LENGTH(tetos) > 0'),
            ],
        };
    }

    /**
     * Nomes das referências e dos usuários das regras da página, uma consulta por tipo.
     *
     * @param  Collection<int, object>  $regras
     * @return array<string, array<int|string, mixed>>
     */
    private function nomes_das_referencias(Collection $regras): array
    {
        $ids = fn (string $camada) => $regras->where('camada', $camada)->pluck('referencia_id')->filter()->unique()->all();
        $usuarios = Usuarios::withTrashed()
            ->whereIn('id', [...$ids('vendedores'), ...$regras->pluck('usuarios_id')->filter()->all()])
            ->get(['id', 'nome', 'ativo', 'deleted_at']);

        return [
            'vendedores' => $usuarios->pluck('nome', 'id')->all(),
            'usuarios' => $usuarios->mapWithKeys(fn (Usuarios $usuario) => [$usuario->id => [
                'id' => $usuario->id,
                'nome' => $usuario->nome,
                'ativo' => (bool) $usuario->ativo,
                'excluido' => $usuario->trashed(),
            ]])->all(),
            'clientes' => Clientes::whereIn('id', $ids('clientes'))->pluck('nome', 'id')->all(),
            'campeonatos' => Campeonatos::whereIn('id', $ids('campeonatos'))->pluck('nome', 'id')->all(),
            'confrontos' => Confrontos::whereIn('id', $ids('confrontos'))->get(['id', 'time_casa', 'time_fora'])
                ->mapWithKeys(fn (Confrontos $confronto) => [$confronto->id => "{$confronto->time_casa} x {$confronto->time_fora}"])->all(),
            'tetos' => [],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $lista_valores
     * @return array<string, float>
     */
    private function somar(Collection $lista_valores): array
    {
        $soma = [];

        foreach ($lista_valores as $valores) {
            foreach ($valores ?? [] as $codigo => $valor) {
                $soma[$codigo] = ($soma[$codigo] ?? 0) + (float) $valor;
            }
        }

        return $soma;
    }
}
