<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CampeonatosRequest;
use App\Http\Resources\CampeonatosResource;
use App\Models\Campeonatos;
use App\Services\AlcanceHierarquia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Campeonatos no painel: listagem, ativar e desativar, favoritar e cadastro manual.
 */
class CampeonatosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private AlcanceHierarquia $alcance) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('campeonatos.listar', only: ['index', 'show']),
            self::permissao_cliente('campeonatos.cadastrar', only: ['store']),
            self::permissao_cliente('campeonatos.editar', only: ['update']),
            self::permissao_cliente('campeonatos.excluir', only: ['destroy']),
            self::permissao_cliente('campeonatos.alterar_situacao', only: ['alterar_situacao']),
            self::permissao_cliente('campeonatos.favoritar', only: ['alterar_favorito']),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // filtros enviados vazios chegam como null e são ignorados
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'ativo' => ['nullable', 'boolean'],
            'favorito' => ['nullable', 'boolean'],
            'manual' => ['nullable', 'boolean'],
            'pais' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'ativo.boolean' => 'O filtro ativo deve ser verdadeiro ou falso.',
            'favorito.boolean' => 'O filtro favorito deve ser verdadeiro ou falso.',
            'manual.boolean' => 'O filtro manual deve ser verdadeiro ou falso.',
            'pais.max' => 'O país deve ter no máximo 100 caracteres.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $campeonatos = $this->com_nao_permitido($request, Campeonatos::query())
            ->when(isset($filtros['busca']), fn (Builder $c) => $c->where('nome', 'like', '%'.addcslashes($filtros['busca'], '%_\\').'%'))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['favorito']), fn (Builder $c) => $c->where('favorito', (bool) $filtros['favorito']))
            ->when(isset($filtros['manual']), fn (Builder $c) => $c->where('manual', (bool) $filtros['manual']))
            ->when(isset($filtros['pais']), fn (Builder $c) => $c->where('pais', $filtros['pais']))
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return CampeonatosResource::collection($campeonatos);
    }

    public function show(Request $request, Campeonatos $campeonato): CampeonatosResource
    {
        return new CampeonatosResource($this->com_nao_permitido($request, Campeonatos::query())->findOrFail($campeonato->id));
    }

    /**
     * Campeonato manual: sem código externo, ativo e não favorito.
     */
    public function store(CampeonatosRequest $request): CampeonatosResource
    {
        $campeonato = new Campeonatos($request->validated());
        $campeonato->forceFill(['manual' => true, 'ativo' => true, 'favorito' => false])->save();

        return new CampeonatosResource($campeonato->refresh());
    }

    public function update(CampeonatosRequest $request, Campeonatos $campeonato): CampeonatosResource
    {
        $this->garantir_manual($campeonato);
        $campeonato->update($request->validated());

        return new CampeonatosResource($campeonato);
    }

    /**
     * Exclusão lógica do campeonato manual e dos confrontos dele.
     */
    public function destroy(Campeonatos $campeonato): Response
    {
        $this->garantir_manual($campeonato);

        DB::transaction(function () use ($campeonato) {
            $campeonato->confrontos()->delete();
            $campeonato->delete();
        });

        return response()->noContent();
    }

    /**
     * Ativa ou desativa para o sistema inteiro (só Admin e Supervisor).
     */
    public function alterar_situacao(Request $request, Campeonatos $campeonato): CampeonatosResource
    {
        $dados = $request->validate(
            ['ativo' => ['required', 'boolean']],
            ['ativo.required' => 'O campo ativo é obrigatório.', 'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.'],
        );

        $campeonato->forceFill(['ativo' => (bool) $dados['ativo']])->save();

        return new CampeonatosResource($campeonato);
    }

    /**
     * Marca ou desmarca como favorito para o sistema inteiro (só Admin e Supervisor).
     */
    public function alterar_favorito(Request $request, Campeonatos $campeonato): CampeonatosResource
    {
        $dados = $request->validate(
            ['favorito' => ['required', 'boolean']],
            ['favorito.required' => 'O campo favorito é obrigatório.', 'favorito.boolean' => 'O campo favorito deve ser verdadeiro ou falso.'],
        );

        $campeonato->forceFill(['favorito' => (bool) $dados['favorito']])->save();

        return new CampeonatosResource($campeonato);
    }

    /**
     * Indica se há um não permitido que vale para quem consulta.
     */
    private function com_nao_permitido(Request $request, Builder $consulta): Builder
    {
        return $consulta->withExists([
            'nao_permitidos as nao_permitido' => fn (Builder $c) => $this->alcance->nao_permitidos_do_usuario($c, $request->user()),
        ]);
    }

    private function garantir_manual(Campeonatos $campeonato): void
    {
        if (! $campeonato->manual) {
            throw ValidationException::withMessages(['campeonato' => 'Só registros manuais podem ser alterados.']);
        }
    }
}
