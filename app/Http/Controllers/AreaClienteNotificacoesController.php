<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\InscricaoNotificacaoRequest;
use App\Http\Resources\InscricaoNotificacaoResource;
use App\Services\InscricoesNotificacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Inscrição do aparelho do cliente nas notificações push (spec 010, FR-040 e FR-041): consultar,
 * gravar (com as preferências por tipo) e desativar, sempre pelo endpoint do aparelho.
 */
class AreaClienteNotificacoesController extends Controller
{
    use RespostasApostas;

    private const MAXIMO_GRAVACOES = 20;

    public function show(Request $request, InscricoesNotificacoes $inscricoes): JsonResponse
    {
        $inscricao = $inscricoes->buscar($request->user('clientes'), (string) $request->query('endpoint', ''));

        abort_if($inscricao === null, 404, 'Notificações não ativadas neste aparelho.');

        return response()->json(['data' => new InscricaoNotificacaoResource($inscricao)]);
    }

    public function update(InscricaoNotificacaoRequest $request, InscricoesNotificacoes $inscricoes): JsonResponse
    {
        $cliente = $request->user('clientes');

        $this->limitar_tentativas('inscricao_notificacao|cliente|'.$cliente->id, self::MAXIMO_GRAVACOES);

        $inscricao = $inscricoes->gravar($cliente, $request->validated(), $request->userAgent());

        return response()->json(['data' => new InscricaoNotificacaoResource($inscricao)]);
    }

    public function destroy(Request $request, InscricoesNotificacoes $inscricoes): Response
    {
        $inscricoes->remover($request->user('clientes'), (string) $request->query('endpoint', ''));

        return response()->noContent();
    }
}
