<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirGerencia;
use App\Http\Requests\StoreUsuariosRequest;
use App\Http\Requests\UpdateUsuariosRequest;
use App\Http\Resources\UsuariosResource;
use App\Models\Usuarios;
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
     * Permissão exigida por ação. O update checa as permissões por campo.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:usuarios.listar', only: ['index']),
            new Middleware('permission:usuarios.consultar', only: ['show']),
            new Middleware('permission:usuarios.cadastrar', only: ['store']),
            new Middleware('permission:usuarios.excluir', only: ['destroy']),
        ];
    }

    /**
     * Lista a sub-hierarquia de quem solicita, com filtros, busca e paginação (máximo 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'funcao' => ['sometimes', Rule::enum(Funcao::class)],
            'ativo' => ['sometimes', 'boolean'],
            'busca' => ['sometimes', 'string', 'max:255'],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ], [
            'funcao.enum' => 'A função informada é inválida.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 255 caracteres.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $usuarios = $this->consulta_sub_hierarquia($request->user(), $filtros)
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return UsuariosResource::collection($usuarios);
    }

    /**
     * Cadastra um subordinado vinculado a quem solicita, com o papel da função e as
     * permissões padrão dela; todo usuário novo nasce ativo.
     */
    public function store(StoreUsuariosRequest $request): UsuariosResource
    {
        $usuario = DB::transaction(function () use ($request) {
            $usuario = Usuarios::create([
                ...$request->safe()->except('funcao'),
                'usuarios_id' => $request->user()->id,
                'ativo' => true,
            ]);

            $usuario->assignRole($request->validated('funcao'));
            $usuario->atribuir_permissoes_padrao($request->user());

            return $usuario;
        });

        // o resource responde 201 por se tratar de um registro recém-criado
        return new UsuariosResource($usuario);
    }

    public function show(Request $request, Usuarios $usuario): UsuariosResource
    {
        $this->garantir_gerencia($request, $usuario);

        return new UsuariosResource($usuario);
    }

    /**
     * Edita dados (exige usuarios.editar) e/ou a situação (exige usuarios.alterar_situacao),
     * de forma independente. Senha ausente ou vazia mantém a atual; a situação é aplicada
     * em cascata na sub-hierarquia.
     */
    public function update(UpdateUsuariosRequest $request, Usuarios $usuario): UsuariosResource
    {
        $this->garantir_gerencia($request, $usuario);

        if ($request->altera_dados()) {
            $this->garantir_permissao($request, 'usuarios.editar');

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

        $usuario->excluir_em_cascata();

        return response()->noContent();
    }

    private function garantir_permissao(Request $request, string $permissao): void
    {
        abort_unless($request->user()->hasPermissionTo($permissao), 403, 'Você não tem permissão para esta ação.');
    }

    /**
     * Consulta dos usuários abaixo do solicitante, com os filtros opcionais da listagem.
     */
    private function consulta_sub_hierarquia(Usuarios $solicitante, array $filtros): Builder
    {
        return Usuarios::with('roles')
            ->whereIn('id', $solicitante->ids_sub_hierarquia())
            ->when(isset($filtros['funcao']), fn (Builder $consulta) => $consulta->role($filtros['funcao']))
            ->when(isset($filtros['ativo']), fn (Builder $consulta) => $consulta->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['busca']), fn (Builder $consulta) => $consulta->where(
                fn (Builder $busca) => $busca->where('nome', 'like', "%{$filtros['busca']}%")
                    ->orWhere('login', 'like', "%{$filtros['busca']}%")
            ));
    }
}
