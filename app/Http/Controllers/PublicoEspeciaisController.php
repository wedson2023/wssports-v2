<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListagemEspeciaisRequest;
use App\Services\IdentificacaoPublico;
use App\Services\ListagemEspeciais;
use Illuminate\Http\JsonResponse;

/**
 * Listagem pública das categorias especiais (sem login). Aceita também o token de um cliente ou de
 * um usuário do painel, para aplicar as regras de quem está vendo.
 */
class PublicoEspeciaisController extends Controller
{
    public function index(ListagemEspeciaisRequest $request, IdentificacaoPublico $identificacao, ListagemEspeciais $listagem): JsonResponse
    {
        return response()->json($listagem->listar($identificacao->identificar($request), $request->validated()));
    }
}
