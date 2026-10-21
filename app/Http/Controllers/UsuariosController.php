<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirGerencia;
use App\Http\Requests\StoreUsuariosRequest;
use App\Http\Requests\UpdateUsuariosRequest;
use App\Http\Resources\UsuariosResource;
use App\Models\Usuarios;
use App\Services\ConfiguracoesVendedores;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuariosController extends Controller implements HasMiddleware
{
    use GarantirGerencia;

    /**
     * Permissão exigida por ação. Cadastrar, editar, excluir e restaurar checam a permissão da função
     * alvo (spec 015); o update checa as permissões por campo.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:usuarios.listar', only: ['index', 'excluidos']),
            new Middleware('permission:usuarios.consultar', only: ['show']),
        ];
    }

    /**
     * Lista a sub-hierarquia de quem solicita, com filtros, busca, colunas da tela e paginação
     * (padrão 15, máximo 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->filtros($request);

        $usuarios = $this->consulta_sub_hierarquia($request->user()->ids_sub_hierarquia(), $filtros)
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return UsuariosResource::collection($usuarios);
    }

    /**
     * Lixeira: excluídos da rede de quem solicita, inclusive os que estão abaixo de outro excluído.
     */
    public function excluidos(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->filtros($request);

        $usuarios = $this->consulta_sub_hierarquia($request->user()->ids_sub_hierarquia_com_excluidos(), $filtros)
            ->onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return UsuariosResource::collection($usuarios);
    }

    /**
     * Cadastra um usuário de qualquer função abaixo de quem solicita, com o papel da função e as
     * permissões padrão dela; fora da função imediatamente abaixo, fica sob o superior escolhido.
     * Todo usuário novo nasce ativo.
     */
    public function store(StoreUsuariosRequest $request): UsuariosResource
    {
        $funcao = Funcao::from($request->validated('funcao'));
        $this->garantir_permissao_funcao($request, 'cadastrar', $funcao);

        $usuario = DB::transaction(function () use ($request, $funcao) {
            $usuario = Usuarios::create([
                ...$request->safe()->except(['funcao', 'comissao_1', 'comissao_2', 'comissao_3']),
                'usuarios_id' => $request->exige_superior($funcao) ? (int) $request->validated('usuarios_id') : $request->user()->id,
                'ativo' => true,
            ]);

            $usuario->assignRole($funcao->value);
            $usuario->atribuir_permissoes_padrao($request->user());

            // vendedor novo nasce com a configuração de um colega (ou com os valores padrão) e, se
            // vierem, com as comissões informadas no cadastro
            if ($funcao === Funcao::Vendedor) {
                $configuracoes = app(ConfiguracoesVendedores::class);
                $configuracoes->criar_para($usuario);
                $configuracoes->aplicar_comissoes_cadastro(
                    $usuario,
                    $request->validated('comissao_1'),
                    $request->validated('comissao_2'),
                    $request->validated('comissao_3'),
                );
            }

            return $usuario;
        });

        // o resource responde 201 por se tratar de um registro recém-criado
        return new UsuariosResource($usuario);
    }

    public function show(Request $request, Usuarios $usuario): UsuariosResource
    {
        $this->garantir_gerencia($request, $usuario);

        return new UsuariosResource($usuario->load('superior:id,nome'));
    }

    /**
     * Edita dados e superior (exige usuarios.editar_<função>) e/ou a situação (exige
     * usuarios.alterar_situacao), de forma independente. Senha ausente ou vazia mantém a atual; a
     * situação é aplicada em cascata na sub-hierarquia.
     */
    public function update(UpdateUsuariosRequest $request, Usuarios $usuario): UsuariosResource
    {
        $this->garantir_gerencia($request, $usuario);

        if ($request->altera_dados()) {
            $this->garantir_permissao_funcao($request, 'editar', $usuario->funcao());

            $dados = $request->safe()->except(['password', 'ativo']);

            if ($request->filled('password')) {
                $dados['password'] = $request->validated('password');
            }

            $usuario->update($dados);
        }

        if ($request->has('ativo')) {
            $this->garantir_permissao($request, 'usuarios.alterar_situacao');

            if ($request->boolean('ativo') !== $usuario->ativo) {
                $usuario->alterar_situacao_em_cascata($request->boolean('ativo'));
            }
        }

        return new UsuariosResource($usuario);
    }

    /**
     * Exclusão lógica em cascata: o usuário e toda a sua sub-hierarquia.
     */
    public function destroy(Request $request, Usuarios $usuario): Response
    {
        $this->garantir_gerencia($request, $usuario);
        $this->garantir_permissao_funcao($request, 'excluir', $usuario->funcao());

        $usuario->excluir_em_cascata();

        return response()->noContent();
    }

    /**
     * Restaura da Lixeira o usuário e os excluídos junto com ele; o superior precisa estar ativo na
     * árvore (não excluído).
     */
    public function restaurar(Request $request, Usuarios $usuario): UsuariosResource
    {
        abort_unless(
            $usuario->trashed() && in_array($usuario->id, $request->user()->ids_sub_hierarquia_com_excluidos(), true),
            404,
            'Usuário não encontrado.',
        );

        $this->garantir_permissao_funcao($request, 'excluir', $usuario->funcao());

        abort_if(
            $usuario->usuarios_id !== null && Usuarios::onlyTrashed()->whereKey($usuario->usuarios_id)->exists(),
            422,
            'Restaure antes o superior.',
        );

        $usuario->restaurar_em_cascata();

        return new UsuariosResource($usuario->refresh());
    }

    private function garantir_permissao(Request $request, string $permissao): void
    {
        abort_unless($request->user()->hasPermissionTo($permissao), 403, 'Você não tem permissão para esta ação.');
    }

    /**
     * A permissão da ação sobre a função alvo, que a função de quem solicita precisa poder usar.
     */
    private function garantir_permissao_funcao(Request $request, string $acao, ?Funcao $alvo): void
    {
        abort_unless(
            $alvo !== null && Funcao::usuario_pode($request->user(), Funcao::permissao_usuarios($acao, $alvo)),
            403,
            'Você não tem permissão para esta ação.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        return $request->validate([
            'funcao' => ['sometimes', Rule::enum(Funcao::class)],
            'ativo' => ['sometimes', 'boolean'],
            'busca' => ['sometimes', 'nullable', 'string', 'max:255'],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ], [
            'funcao.enum' => 'A função informada é inválida.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 255 caracteres.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);
    }

    /**
     * Usuários dos ids informados, com os filtros da listagem e as colunas da tela (spec 015, R-06):
     * superior, comissões 1/2/3 do vendedor e as quantidades de subordinados ativos, sem consulta por
     * linha.
     *
     * @param  list<int>  $ids
     */
    private function consulta_sub_hierarquia(array $ids, array $filtros): Builder
    {
        $ativos_abaixo_dos_diretos = DB::table('usuarios as neto')
            ->join('usuarios as direto', 'direto.id', '=', 'neto.usuarios_id')
            ->whereColumn('direto.usuarios_id', 'usuarios.id')
            ->where('neto.ativo', true)
            ->whereNull('neto.deleted_at')
            ->whereNull('direto.deleted_at')
            ->selectRaw('COUNT(*)');

        return Usuarios::query()
            ->select('usuarios.*')
            ->with(['roles', 'superior:id,nome'])
            ->when(($filtros['funcao'] ?? null) === Funcao::Vendedor->value, fn (Builder $consulta) => $consulta->with('configuracoes'))
            ->withCount(['subordinados as quantidade_diretos' => fn (Builder $consulta) => $consulta->where('ativo', true)])
            ->selectSub($ativos_abaixo_dos_diretos, 'quantidade_netos')
            ->whereIn('usuarios.id', $ids)
            ->when(isset($filtros['funcao']), fn (Builder $consulta) => $consulta->role($filtros['funcao']))
            ->when(isset($filtros['ativo']), fn (Builder $consulta) => $consulta->where('ativo', (bool) $filtros['ativo']))
            ->when(filled($filtros['busca'] ?? null), fn (Builder $consulta) => $consulta->where(
                fn (Builder $busca) => $busca->where('nome', 'like', "%{$filtros['busca']}%")
                    ->orWhere('login', 'like', "%{$filtros['busca']}%")
            ));
    }
}
