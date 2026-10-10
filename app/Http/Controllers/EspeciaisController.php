<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEspecial;
use App\Enums\SituacaoPalpite;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\EspeciaisRequest;
use App\Http\Resources\EspeciaisResource;
use App\Models\Especiais;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Categorias especiais no painel: listagem, cadastro (com as opções), edição e remoção. Só Admin e
 * Supervisor (spec 006, FR-012).
 */
class EspeciaisController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('especiais.listar', only: ['index', 'show']),
            self::permissao_cliente('especiais.gerenciar', only: ['store', 'update', 'destroy']),
        ];
    }

    /**
     * Palpites ativos em apostas ativas: os que contam para recusar a remoção.
     */
    public static function palpites_em_apostas_ativas(Builder $palpites): Builder
    {
        return $palpites->where('situacao', SituacaoPalpite::Ativo->value)
            ->whereHas('aposta', fn (Builder $aposta) => $aposta->where('situacao', SituacaoAposta::Ativa->value));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // filtros enviados vazios chegam como null e são ignorados
        $filtros = $request->validate([
            'situacao' => ['nullable', Rule::enum(SituacaoEspecial::class)],
            'busca' => ['nullable', 'string', 'max:150'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'situacao.enum' => 'A situação informada é inválida.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $especiais = Especiais::with('opcoes')
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('situacao', $filtros['situacao']))
            ->when(filled($filtros['busca'] ?? null), fn (Builder $c) => $c->where('nome', 'like', '%'.addcslashes($filtros['busca'], '%_\\').'%'))
            ->orderBy('data_limite')
            ->orderBy('nome')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return EspeciaisResource::collection($especiais);
    }

    public function show(Especiais $especial): EspeciaisResource
    {
        $especial->load('opcoes')->loadCount(['palpites' => fn (Builder $palpites) => self::palpites_em_apostas_ativas($palpites)]);

        return new EspeciaisResource($especial);
    }

    public function store(EspeciaisRequest $request): EspeciaisResource
    {
        $especial = DB::transaction(function () use ($request) {
            $especial = Especiais::create($request->dados_categoria());
            $especial->opcoes()->createMany($request->validated('opcoes'));

            return $especial;
        });

        return new EspeciaisResource($especial->load('opcoes'));
    }

    public function update(EspeciaisRequest $request, Especiais $especial): EspeciaisResource
    {
        $this->garantir_aguardando($especial);

        $especial->update($request->dados_categoria());

        return new EspeciaisResource($especial->load('opcoes'));
    }

    public function destroy(Especiais $especial): Response
    {
        $this->garantir_aguardando($especial);

        if (self::palpites_em_apostas_ativas($especial->palpites()->getQuery())->exists()) {
            throw ValidationException::withMessages(['especial' => 'A categoria tem palpites em apostas ativas: cancele a categoria em vez de remover.']);
        }

        $especial->delete();

        return response()->noContent();
    }

    /**
     * Categoria encerrada ou cancelada não muda mais.
     */
    public static function garantir_aguardando(Especiais $especial): void
    {
        if ($especial->situacao !== SituacaoEspecial::Aguardando) {
            throw ValidationException::withMessages(['especial' => 'A categoria já foi encerrada ou cancelada.']);
        }
    }
}
