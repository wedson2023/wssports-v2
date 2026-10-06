<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListagemPublicaRequest;
use App\Services\IdentificacaoPublico;
use App\Services\ListagemConfrontos;
use Illuminate\Http\JsonResponse;

/**
 * Listagem pública de jogos (sem login), que alterna entre pré-jogo e ao vivo. Aceita também o
 * token de um cliente ou de um usuário do painel, para aplicar as regras de quem está vendo.
 */
class PublicoConfrontosController extends Controller
{
    public function index(ListagemPublicaRequest $request, IdentificacaoPublico $identificacao, ListagemConfrontos $listagem): JsonResponse
    {
        $publico = $identificacao->identificar($request);
        $filtros = $request->validated();

        $resposta = ($filtros['tipo'] ?? 'pre_jogo') === 'ao_vivo'
            ? $listagem->ao_vivo($publico, $filtros)
            : $listagem->pre_jogo($publico, $filtros);

        return response()->json($resposta);
    }
}
