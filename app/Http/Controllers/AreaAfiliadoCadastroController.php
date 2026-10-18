<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastroPublicoAfiliadoRequest;
use App\Services\CadastroAfiliados;
use Illuminate\Http\JsonResponse;

class AreaAfiliadoCadastroController extends Controller
{
    /**
     * Cadastro público do afiliado: fica em análise até a banca aprovar; não devolve token.
     */
    public function store(CadastroPublicoAfiliadoRequest $request, CadastroAfiliados $cadastro): JsonResponse
    {
        $cadastro->cadastrar_publico($request->validated());

        return response()->json(['message' => 'Cadastro recebido! Vamos analisar e avisaremos por e-mail.'], 201);
    }
}
