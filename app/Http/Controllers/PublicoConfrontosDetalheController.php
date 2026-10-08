<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListagemPublicaRequest;
use App\Services\DetalheConfrontos;
use App\Services\IdentificacaoPublico;
use Carbon\CarbonTimeZone;
use Illuminate\Http\JsonResponse;

/**
 * Detalhe público de um confronto com todas as cotações (pré-jogo e ao vivo), com o mesmo limite
 * de requisições e as mesmas regras de quem está vendo da listagem.
 */
class PublicoConfrontosDetalheController extends Controller
{
    private const FUSO_PADRAO = '-03:00';

    public function __construct(private IdentificacaoPublico $identificacao, private DetalheConfrontos $detalhe) {}

    public function pre_jogo(ListagemPublicaRequest $request, int $confronto): JsonResponse
    {
        $publico = $this->identificacao->identificar($request);

        return response()->json(['data' => $this->detalhe->pre_jogo($publico, $confronto, $this->fuso($request))]);
    }

    public function ao_vivo(ListagemPublicaRequest $request, int $confronto_ao_vivo): JsonResponse
    {
        $publico = $this->identificacao->identificar($request);

        return response()->json(['data' => $this->detalhe->ao_vivo($publico, $confronto_ao_vivo, $this->fuso($request))]);
    }

    private function fuso(ListagemPublicaRequest $request): CarbonTimeZone
    {
        return new CarbonTimeZone($request->validated('fuso_horario') ?? self::FUSO_PADRAO);
    }
}
