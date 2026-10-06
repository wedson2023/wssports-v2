<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AlvoRegra;
use App\Http\Requests\NaoPermitidosRequest;
use App\Http\Resources\NaoPermitidosResource;
use App\Services\AlcanceHierarquia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Listar, marcar e desmarcar não permitidos, comum às três tabelas (campeonatos, confrontos do
 * pré-jogo e do ao vivo). Desmarcar é exclusão lógica; marcar de novo restaura o registro.
 */
trait GerenciarNaoPermitidos
{
    /**
     * Classe do model da tabela de não permitidos.
     *
     * @return class-string<Model>
     */
    abstract protected function modelo(): string;

    /**
     * Coluna do item escondido (campeonatos_id ou confrontos_id).
     */
    abstract protected function coluna_item(): string;

    public function index(Request $request, AlcanceHierarquia $alcance): AnonymousResourceCollection
    {
        $coluna = $this->coluna_item();

        // filtros enviados vazios chegam como null e são ignorados
        $filtros = $request->validate([
            $coluna => ['nullable', 'integer'],
            'alvo' => ['nullable', Rule::enum(AlvoRegra::class)],
            'usuarios_id' => ['nullable', 'integer'],
            'clientes_id' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            "{$coluna}.integer" => 'O item deve ser um número inteiro.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
            'usuarios_id.integer' => 'O usuário deve ser um número inteiro.',
            'clientes_id.integer' => 'O cliente deve ser um número inteiro.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $donos = $alcance->ids_donos_visiveis($request->user());

        $registros = $this->modelo()::query()
            ->when($donos !== null, fn (Builder $c) => $c->where('alvo', AlvoRegra::Vendedores)->whereIn('usuarios_id', $donos))
            ->when(isset($filtros[$coluna]), fn (Builder $c) => $c->where($coluna, $filtros[$coluna]))
            ->when(isset($filtros['alvo']), fn (Builder $c) => $c->where('alvo', $filtros['alvo']))
            ->when(isset($filtros['usuarios_id']), fn (Builder $c) => $c->where('usuarios_id', $filtros['usuarios_id']))
            ->when(isset($filtros['clientes_id']), fn (Builder $c) => $c->where('clientes_id', $filtros['clientes_id']))
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return NaoPermitidosResource::collection($registros);
    }

    public function store(NaoPermitidosRequest $request, AlcanceHierarquia $alcance): JsonResponse
    {
        $alvo = AlvoRegra::from($request->validated('alvo'));
        $clientes_id = $request->validated('clientes_id');
        $dono = $alcance->garantir_alvo($request->user(), $alvo, $request->validated('usuarios_id'), $clientes_id);

        $registro = $this->modelo()::withTrashed()->firstOrNew([
            $this->coluna_item() => $request->validated($this->coluna_item()),
            'alvo' => $alvo,
            'usuarios_id' => $dono?->id,
            'clientes_id' => $alvo === AlvoRegra::Clientes ? $clientes_id : null,
        ]);

        $novo = ! $registro->exists;

        if ($registro->trashed()) {
            $registro->restore();
        } elseif ($novo) {
            $registro->save();
        }

        return (new NaoPermitidosResource($registro))->response()->setStatusCode($novo ? 201 : 200);
    }

    public function destroy(Request $request, AlcanceHierarquia $alcance): Response
    {
        $registro = $this->modelo()::find($request->route('registro'));

        abort_if($registro === null, 404, 'Registro não encontrado.');

        $donos = $alcance->ids_donos_visiveis($request->user());

        abort_unless(
            $donos === null || ($registro->alvo === AlvoRegra::Vendedores && in_array($registro->usuarios_id, $donos, true)),
            403,
            'Você não tem permissão para esta ação.',
        );

        $registro->delete();

        return response()->noContent();
    }
}
