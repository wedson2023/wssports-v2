<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoEncerramento;
use App\Exceptions\OfertaAlteradaException;
use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\EncerrarApostaRequest;
use App\Http\Resources\EncerramentoResource;
use App\Models\Apostas;
use App\Models\Clientes;
use App\Services\AcessoAssistido;
use App\Services\BilheteTempoReal;
use App\Services\EncerramentoApostas;
use App\Support\FusoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Encerramento de aposta pelo cliente dono (spec 010, contracts/api.md seção 2). No acesso
 * assistido, o autor gravado é o usuário do painel (FR-016, FR-035).
 */
class AreaClienteEncerramentosController extends Controller
{
    use RespostasApostas;

    private const MAXIMO_PEDIDOS = 10;

    public function store(EncerrarApostaRequest $request, string $codigo, EncerramentoApostas $encerramentos, BilheteTempoReal $bilhete, AcessoAssistido $assistido): JsonResponse
    {
        /** @var Clientes $cliente */
        $cliente = $request->user('clientes');

        $this->limitar_tentativas('encerramento|cliente|'.$cliente->id, self::MAXIMO_PEDIDOS);

        try {
            [$encerramento, $repetido] = $encerramentos->pedir($cliente, $assistido->autor($request), $codigo, $request->dados(), $request->ip(), $request->userAgent());
        } catch (OfertaAlteradaException $excecao) {
            return $this->oferta_alterada($request, $codigo, $cliente, $bilhete, $excecao->getMessage());
        }

        if ($encerramento->situacao === SituacaoEncerramento::Recusado && ! $repetido) {
            return $this->oferta_alterada($request, $codigo, $cliente, $bilhete, (string) $encerramento->motivo_recusa);
        }

        $status = match (true) {
            $repetido => 200,
            $encerramento->situacao === SituacaoEncerramento::EmAnálise => 202,
            default => 201,
        };

        return response()->json(['data' => new EncerramentoResource($encerramento)], $status);
    }

    public function show(Request $request, string $codigo, string $chave): JsonResponse
    {
        $aposta = Apostas::buscar_pelo_codigo($codigo)->where('clientes_id', $request->user('clientes')->id)->first();
        $encerramento = $aposta?->encerramentos()->where('chave_idempotencia', $chave)->first();

        abort_if($encerramento === null, 404, 'Encerramento não encontrado.');

        return response()->json(['data' => new EncerramentoResource($encerramento)]);
    }

    /**
     * 409 com o bloco de encerramento atual, para a página mostrar a oferta nova (R-09).
     */
    private function oferta_alterada(Request $request, string $codigo, Clientes $cliente, BilheteTempoReal $bilhete, string $mensagem): JsonResponse
    {
        $aposta = Apostas::buscar_pelo_codigo($codigo)->where('clientes_id', $cliente->id)->firstOrFail();

        return response()->json([
            'message' => $mensagem,
            'encerramento' => $bilhete->encerramento($aposta, null, null, FusoSistema::do_pedido($request->input('fuso_horario'))),
        ], 409);
    }
}
