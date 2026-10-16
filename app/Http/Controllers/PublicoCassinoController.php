<?php

namespace App\Http\Controllers;

use App\Exceptions\FalhaProvedorCassinoException;
use App\Http\Requests\ListagemJogosCassinoRequest;
use App\Http\Resources\JogoCassinoResource;
use App\Models\CassinosJogos;
use App\Models\Configuracoes;
use App\Services\AberturaJogosCassino;
use App\Services\ListagemCassino;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Jogos do cassino para o site, sem login (spec 011, contracts/api.md §1).
 */
class PublicoCassinoController extends Controller
{
    public function __construct(private ListagemCassino $listagem) {}

    public function inicio(ListagemJogosCassinoRequest $request): JsonResponse
    {
        $this->garantir_cassino_habilitado();

        return response()->json($this->listagem->inicio($request->aparelho()));
    }

    public function jogos(ListagemJogosCassinoRequest $request): AnonymousResourceCollection
    {
        $this->garantir_cassino_habilitado();

        return JogoCassinoResource::collection($this->listagem->jogos($request->aparelho(), $request->validated()));
    }

    /**
     * Demo do jogo, sem login, sem sessão e sem saldo (R-10).
     */
    public function demo(CassinosJogos $jogo, AberturaJogosCassino $abertura): JsonResponse
    {
        $this->garantir_cassino_habilitado();

        try {
            return response()->json($abertura->demo($jogo));
        } catch (FalhaProvedorCassinoException $erro) {
            return response()->json(['message' => $erro->getMessage()], 503);
        }
    }

    private function garantir_cassino_habilitado(): void
    {
        abort_unless(Configuracoes::atual()->cassino_habilitado, 403, 'Acesso ao cassino não está disponível.');
    }
}
