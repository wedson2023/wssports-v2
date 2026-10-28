<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEspecial;
use App\Enums\SituacaoPalpite;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\EspeciaisRequest;
use App\Http\Requests\FiltrosEspeciaisPainelRequest;
use App\Http\Resources\EspeciaisResource;
use App\Models\Especiais;
use App\Services\ExposicaoEspeciais;
use App\Services\ListagemEspeciaisPainel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Categorias especiais no painel: listagem, cadastro (com as opções), edição e remoção. Só Admin e
 * Supervisor (spec 006, FR-012). Na tela Especiais (spec 024), também os cartões, a lixeira e a
 * exposição por opção.
 */
class EspeciaisController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('especiais.listar', only: ['index', 'resumo', 'show', 'exposicao']),
            self::permissao_cliente('especiais.gerenciar', only: ['store', 'update', 'destroy', 'restaurar']),
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

    public function index(FiltrosEspeciaisPainelRequest $request, ListagemEspeciaisPainel $listagem): AnonymousResourceCollection
    {
        $especiais = $listagem->consulta($request->user(), $request->filtros())
            ->paginate($request->por_pagina())
            ->withQueryString();

        return EspeciaisResource::collection($especiais)->additional(['alcance' => $listagem->alcance($request->user())]);
    }

    /**
     * Cartões da tela Especiais (spec 024, contracts/api §2.1).
     */
    public function resumo(FiltrosEspeciaisPainelRequest $request, ListagemEspeciaisPainel $listagem): JsonResponse
    {
        return response()->json($listagem->resumo($request->user(), $request->filtros()['busca']));
    }

    /**
     * Exposição por opção na gaveta (spec 024, contracts/api §2.2).
     */
    public function exposicao(Request $request, Especiais $especial, ExposicaoEspeciais $exposicao): JsonResponse
    {
        return response()->json($exposicao->por_opcao($especial, $request->user()));
    }

    /**
     * Volta da lixeira com as opções que tinha, na mesma situação (spec 024, R-11); recusa nome
     * repetido entre as não removidas.
     */
    public function restaurar(Especiais $especial): EspeciaisResource
    {
        abort_unless($especial->trashed(), 404, 'Categoria especial não encontrada.');

        if (Especiais::where('nome', $especial->nome)->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe uma categoria especial com este nome.']);
        }

        $especial->restore();

        return new EspeciaisResource($especial->load('opcoes'));
    }

    public function show(Especiais $especial): EspeciaisResource
    {
        $especial->load(['opcoes', 'encerrado_por_usuario', 'correcoes.usuario'])
            ->loadCount(['palpites' => fn (Builder $palpites) => self::palpites_em_apostas_ativas($palpites)]);

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
