<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\UsuariosConfiguracoesRequest;
use App\Http\Resources\UsuariosConfiguracoesResource;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use App\Services\ConfiguracoesVendedores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações dos vendedores: a hierarquia acima consulta selecionando o gerente e altera
 * escolhendo o alcance (supervisão, gerente ou vendedor).
 */
class UsuariosConfiguracoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private ConfiguracoesVendedores $configuracoes) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('usuarios_configuracoes.editar'),
        ];
    }

    /**
     * Vendedores do gerente (o próprio solicitante ou alguém da sub-hierarquia dele), com as
     * configurações; quem ainda não tem linha a recebe com os valores padrão.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'gerente_id' => ['required', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'gerente_id.required' => 'Informe o gerente.',
            'gerente_id.integer' => 'O gerente deve ser um número inteiro.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $gerente = Usuarios::find($filtros['gerente_id']);
        $solicitante = $request->user();

        // gerente fora da hierarquia é tratado como inexistente, como nas rotas de usuários
        abort_unless(
            $gerente !== null && $gerente->funcao() === Funcao::Gerente
                && ($gerente->id === $solicitante->id || $solicitante->gerencia($gerente)),
            404,
            'Gerente não encontrado.',
        );

        $vendedores = $gerente->subordinados()->role(Funcao::Vendedor->value)->pluck('id');

        // vendedores sem configuração recebem a linha com os valores padrão das colunas
        $vendedores->each(fn (int $id) => UsuariosConfiguracoes::do_vendedor($id));

        $configuracoes = UsuariosConfiguracoes::with('usuario')
            ->whereIn('usuarios_id', $vendedores)
            ->orderBy('usuarios_id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return UsuariosConfiguracoesResource::collection($configuracoes);
    }

    public function update(UsuariosConfiguracoesRequest $request): JsonResponse
    {
        $alvo = Usuarios::findOrFail($request->validated('usuarios_id'));

        $alterados = $this->configuracoes->alterar($request->user(), $alvo, $request->campos());

        return response()->json(['vendedores_alterados' => $alterados]);
    }
}
