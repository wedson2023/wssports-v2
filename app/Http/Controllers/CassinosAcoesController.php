<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CassinosAcoesRequest;
use App\Http\Resources\CassinosAcoesResource;
use App\Models\CassinosAcoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Tipos de ação do provedor do cassino no painel (spec 011, FR-022): o que cada action do writeBet faz
 * no saldo, sem regra fixa no código.
 */
class CassinosAcoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_jogos.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $por_pagina = min(max((int) $request->query('por_pagina', 20), 1), 100);

        return CassinosAcoesResource::collection(CassinosAcoes::orderBy('acao')->paginate($por_pagina, ['*'], 'pagina')->withQueryString());
    }

    /**
     * Ação excluída antes volta a valer (o índice único da ação inclui as excluídas).
     */
    public function store(CassinosAcoesRequest $request): CassinosAcoesResource
    {
        $dados = ['ativo' => true, ...$request->validated()];
        $excluida = CassinosAcoes::onlyTrashed()->where('acao', $dados['acao'])->first();

        if ($excluida !== null) {
            $excluida->restore();
            $excluida->update($dados);

            return new CassinosAcoesResource($excluida);
        }

        return new CassinosAcoesResource(CassinosAcoes::create($dados));
    }

    public function update(CassinosAcoesRequest $request, CassinosAcoes $acao): CassinosAcoesResource
    {
        $acao->update($request->validated());

        return new CassinosAcoesResource($acao);
    }

    /**
     * Exclusão lógica: a ação passa a ser recusada como desconhecida; as rodadas continuam ligadas.
     */
    public function destroy(CassinosAcoes $acao): Response
    {
        $acao->delete();

        return response()->noContent();
    }
}
