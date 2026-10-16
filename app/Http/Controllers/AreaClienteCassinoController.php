<?php

namespace App\Http\Controllers;

use App\Exceptions\FalhaProvedorCassinoException;
use App\Http\Resources\RodadaCassinoResource;
use App\Models\CassinosJogos;
use App\Models\CassinosSessoes;
use App\Models\Configuracoes;
use App\Services\AberturaJogosCassino;
use App\Services\AcessoAssistido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cassino na área do cliente (spec 011): abrir jogo com dinheiro (FR-013 a FR-017) e as rodadas de
 * uma sessão de jogo (FR-028).
 */
class AreaClienteCassinoController extends Controller
{
    public function abrir(Request $request, CassinosJogos $jogo, AberturaJogosCassino $abertura, AcessoAssistido $acesso_assistido): JsonResponse
    {
        abort_if($acesso_assistido->acesso($request) !== null, 403, 'O acesso assistido não pode abrir jogos com o dinheiro do cliente.');
        abort_unless(Configuracoes::atual()->cassino_habilitado, 403, 'Acesso ao cassino não está disponível.');

        // jogo desativado, fora da lista ou sem canal abrível em nenhum aparelho
        abort_unless(CassinosJogos::whereKey($jogo->id)->visiveisEmAlgumAparelho()->exists(), 422, 'Jogo indisponível no momento.');

        try {
            return response()->json($abertura->abrir($request->user('clientes'), $jogo));
        } catch (FalhaProvedorCassinoException $erro) {
            return response()->json(['message' => $erro->getMessage()], 503);
        }
    }

    /**
     * Rodadas de uma sessão do próprio cliente, da primeira para a última (FR-028); vale também no
     * acesso assistido, só para consulta.
     */
    public function rodadas(Request $request, CassinosSessoes $sessao): AnonymousResourceCollection
    {
        abort_unless((int) $sessao->clientes_id === (int) $request->user('clientes')->id, 404, 'Sessão não encontrada.');

        $por_pagina = min(max((int) $request->query('por_pagina', 50), 1), 100);
        $rodadas = $sessao->rodadas()->orderBy('id')->paginate($por_pagina, ['*'], 'pagina')->withQueryString();
        $sessao->load('jogo.provedor');

        return RodadaCassinoResource::collection($rodadas)->additional([
            'sessao' => [
                'id' => $sessao->id,
                'jogo' => $sessao->jogo?->nome ?? $sessao->codigo_provedor,
                'provedor' => $sessao->jogo?->provedor?->nome,
                'total_apostado' => (string) $sessao->total_apostado,
                'total_ganho' => (string) $sessao->total_ganho,
            ],
        ]);
    }
}
