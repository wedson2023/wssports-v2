<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\ApostasRequest;
use App\Http\Resources\ComprovanteApostaResource;
use App\Models\Apostas;
use App\Services\CriacaoApostas;
use App\Support\Apostador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Apostas do cliente pela área do cliente: criar com o próprio saldo e acompanhar a situação. O
 * cliente não cancela nem edita apostas.
 */
class AreaClienteApostasController extends Controller
{
    use RespostasApostas;

    private const MAXIMO_CONSULTAS_SITUACAO = 60;

    public function store(ApostasRequest $request, CriacaoApostas $criacao): JsonResponse
    {
        [$aposta, $repetida] = $criacao->criar(
            Apostador::cliente($request->user('clientes')),
            $request->dados(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->resposta_criacao($aposta, $repetida, mostrar_comissao: false);
    }

    public function situacao(Request $request, string $codigo): JsonResponse
    {
        $cliente = $request->user('clientes');

        $this->limitar_tentativas('situacao_aposta|cliente|'.$cliente->id, self::MAXIMO_CONSULTAS_SITUACAO);

        $aposta = Apostas::buscar_pelo_codigo($codigo)->where('clientes_id', $cliente->id)->first();

        abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

        return response()->json(['data' => new ComprovanteApostaResource($aposta)]);
    }
}
