<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAfiliado;
use App\Http\Controllers\Concerns\GarantirAlcanceAfiliado;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\AprovarAfiliadoRequest;
use App\Http\Requests\RestaurarAfiliadosRequest;
use App\Http\Requests\StoreAfiliadosRequest;
use App\Http\Requests\UpdateAfiliadosRequest;
use App\Http\Resources\AfiliadosResource;
use App\Http\Resources\ClientesResource;
use App\Models\Afiliados;
use App\Models\Usuarios;
use App\Services\AlcanceHierarquia;
use App\Services\CadastroAfiliados;
use App\Services\ListagemAfiliados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestão de afiliados no painel (spec 012): cadastro, consulta, edição, situação, aprovação,
 * exclusão e restauração.
 */
class AfiliadosController extends Controller implements HasMiddleware
{
    use GarantirAlcanceAfiliado, GarantirPermissaoCliente;

    /**
     * Relações devolvidas com o afiliado na consulta e depois das alterações.
     */
    private const RELACOES = ['gerente', 'comissao_vigente', 'meio_pagamento', 'ultima_prestacao'];

    /**
     * Dados únicos que recebem o sufixo de exclusão (FR-048).
     */
    private const DADOS_UNICOS = ['login', 'email', 'cpf', 'codigo'];

    public function __construct(
        private CadastroAfiliados $cadastro,
        private AlcanceHierarquia $alcance,
        private ListagemAfiliados $listagem,
    ) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('afiliados.listar', only: ['index']),
            self::permissao_cliente('afiliados.cadastrar', only: ['store']),
            self::permissao_cliente('afiliados.consultar', only: ['show']),
            self::permissao_cliente('afiliados.editar', only: ['update']),
            self::permissao_cliente('afiliados.alterar_situacao', only: ['alterar_situacao', 'aprovar', 'recusar']),
            self::permissao_cliente('afiliados.excluir', only: ['destroy']),
            self::permissao_cliente('afiliados.restaurar', only: ['excluidos', 'restaurar']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Afiliados::query());
    }

    public function excluidos(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Afiliados::onlyTrashed());
    }

    public function store(StoreAfiliadosRequest $request): JsonResponse
    {
        $afiliado = $this->cadastro->cadastrar_pelo_painel($request->validated(), $request->user());

        return (new AfiliadosResource($this->carregar($afiliado)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Afiliados $afiliado): AfiliadosResource
    {
        $this->garantir_alcance($request, $afiliado);

        return new AfiliadosResource($this->carregar($afiliado));
    }

    /**
     * Dados e Pix; senha nova faz os tokens já emitidos deixarem de valer (FR-047).
     */
    public function update(UpdateAfiliadosRequest $request, Afiliados $afiliado): AfiliadosResource
    {
        $this->garantir_alcance($request, $afiliado);

        $dados = $request->validated();

        DB::transaction(function () use ($afiliado, $dados) {
            $afiliado->update(Arr::except($dados, ['pix_tipo_chave', 'pix_chave', 'password_confirmation']));

            if (isset($dados['pix_tipo_chave'])) {
                $afiliado->meio_pagamento()->updateOrCreate([], Arr::only($dados, ['pix_tipo_chave', 'pix_chave']));
            }

            if (isset($dados['password'])) {
                $afiliado->invalidar_tokens();
            }
        });

        return new AfiliadosResource($this->carregar($afiliado->refresh()));
    }

    /**
     * Ativar e inativar; em análise e recusado usam aprovar/recusar. Inativar corta o acesso na hora,
     * mas os clientes continuam gerando comissão (FR-029).
     */
    public function alterar_situacao(Request $request, Afiliados $afiliado): AfiliadosResource
    {
        $this->garantir_alcance($request, $afiliado);

        $situacao = SituacaoAfiliado::from($request->validate(
            ['situacao' => ['required', Rule::in([SituacaoAfiliado::Ativo->value, SituacaoAfiliado::Inativo->value])]],
            ['situacao.required' => 'Informe a situação.', 'situacao.in' => 'A situação deve ser Ativo ou Inativo.'],
        )['situacao']);

        if (! in_array($afiliado->situacao, [SituacaoAfiliado::Ativo, SituacaoAfiliado::Inativo], true)) {
            throw ValidationException::withMessages(['situacao' => 'Use aprovar ou recusar para cadastros em análise ou recusados.']);
        }

        $afiliado->forceFill(['situacao' => $situacao])->save();

        if ($situacao === SituacaoAfiliado::Inativo) {
            $afiliado->invalidar_tokens();
        }

        return new AfiliadosResource($this->carregar($afiliado));
    }

    /**
     * Exclusão lógica com sufixo nos dados únicos, que ficam livres para novos cadastros; o código
     * continua reconhecido pelo valor original (VinculoAfiliados).
     */
    public function destroy(Afiliados $afiliado): Response
    {
        $sufixo = '_deleted_'.now()->timestamp;

        DB::transaction(function () use ($afiliado, $sufixo) {
            foreach (self::DADOS_UNICOS as $campo) {
                if ($afiliado->{$campo} !== null) {
                    $afiliado->{$campo} .= $sufixo;
                }
            }

            $afiliado->save();
            $afiliado->invalidar_tokens();
            $afiliado->delete();
        });

        return response()->noContent();
    }

    /**
     * Devolve os dados únicos originais; se algum já estiver em uso, a restauração só é concluída
     * com um valor novo informado no corpo (FR-049).
     */
    public function restaurar(RestaurarAfiliadosRequest $request, Afiliados $afiliado): AfiliadosResource|JsonResponse
    {
        if (! $afiliado->trashed()) {
            return response()->json(['message' => 'Afiliado não está excluído.'], 422);
        }

        $dados = [];

        foreach (self::DADOS_UNICOS as $campo) {
            $original = $afiliado->{$campo} === null ? null : preg_replace(ClientesResource::SUFIXO_EXCLUSAO, '', $afiliado->{$campo});
            $dados[$campo] = $request->has($campo) ? $request->validated($campo) : $original;
        }

        $conflitos = $this->conflitos_de_dados_unicos($dados, $afiliado->id);

        if ($conflitos !== []) {
            return response()->json([
                'message' => 'Há dados já em uso por outro cadastro.',
                'errors' => array_fill_keys($conflitos, ['Já está em uso; informe um novo valor.']),
            ], 422);
        }

        DB::transaction(function () use ($afiliado, $dados) {
            $afiliado->forceFill($dados);
            $afiliado->restore();
        });

        return new AfiliadosResource($this->carregar($afiliado));
    }

    /**
     * Aprovar e recusar o cadastro público é só de Admin e Supervisor (FR-014).
     */
    public function aprovar(AprovarAfiliadoRequest $request, Afiliados $afiliado): AfiliadosResource
    {
        $this->alcance->garantir_gestor_geral($request->user());

        return new AfiliadosResource($this->carregar($this->cadastro->aprovar($afiliado, $request->user(), $request->validated())));
    }

    public function recusar(Request $request, Afiliados $afiliado): AfiliadosResource
    {
        $this->alcance->garantir_gestor_geral($request->user());

        $motivo = $request->validate(
            ['motivo' => ['nullable', 'string', 'max:255']],
            ['motivo.string' => 'O motivo deve ser um texto.', 'motivo.max' => 'O motivo deve ter no máximo 255 caracteres.'],
        )['motivo'] ?? null;

        return new AfiliadosResource($this->carregar($this->cadastro->recusar($afiliado, $request->user(), $motivo)));
    }

    /**
     * @param  array{login: ?string, email: ?string, cpf: ?string, codigo: ?string}  $dados
     * @return list<string> campos em conflito
     */
    private function conflitos_de_dados_unicos(array $dados, int $ignorar_id): array
    {
        $em_uso = fn (string $campo) => $dados[$campo] !== null
            && Afiliados::withTrashed()->where('id', '!=', $ignorar_id)->where($campo, $dados[$campo])->exists();

        $login_do_painel = $dados['login'] !== null
            && Usuarios::withTrashed()->whereRaw('LOWER(login) = ?', [mb_strtolower($dados['login'])])->exists();

        return array_values(array_filter(
            self::DADOS_UNICOS,
            fn (string $campo) => $em_uso($campo) || ($campo === 'login' && $login_do_painel),
        ));
    }

    /**
     * Listagem paginada (padrão 20, máximo 100) com os totais do período; Gerente só os seus.
     */
    private function listar(Request $request, Builder $consulta): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'situacao' => ['nullable', Rule::enum(SituacaoAfiliado::class)],
            'busca' => ['nullable', 'string', 'max:150'],
            'usuarios_id' => ['nullable', 'integer'],
            // afiliados antigos ainda sem gerente responsável (spec 019, FR-003d)
            'sem_gerente' => ['nullable', 'boolean'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'situacao.enum' => 'A situação deve ser Em análise, Ativo, Inativo ou Recusado.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca é longa demais.',
            'usuarios_id.integer' => 'O gerente informado é inválido.',
            'sem_gerente.boolean' => 'O filtro sem gerente deve ser verdadeiro ou falso.',
            ...ListagemAfiliados::mensagens_extrato(),
        ]);

        $afiliados = $this->listagem
            ->consulta_gestao($this->aplicar_alcance($request, $consulta), $filtros)
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return AfiliadosResource::collection($afiliados);
    }

    /**
     * Afiliado com as relações da resposta e o valor a receber (lançamentos em aberto).
     */
    private function carregar(Afiliados $afiliado): Afiliados
    {
        return $afiliado->load(self::RELACOES)
            ->loadSum(['lancamentos as valor_a_receber' => fn ($consulta) => $consulta->emAberto()], 'valor');
    }
}
