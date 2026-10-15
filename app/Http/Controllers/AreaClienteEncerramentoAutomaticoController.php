<?php

namespace App\Http\Controllers;

use App\Http\Requests\EncerramentoAutomaticoRequest;
use App\Services\AcessoAssistido;
use App\Services\EncerramentosAutomaticos;
use App\Support\FusoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Encerramento automático por valor-alvo (spec 010, FR-030 e FR-031): programar, alterar e
 * desativar. No acesso assistido, o autor gravado é o usuário do painel.
 */
class AreaClienteEncerramentoAutomaticoController extends Controller
{
    public function update(EncerramentoAutomaticoRequest $request, string $codigo, EncerramentosAutomaticos $automaticos, AcessoAssistido $assistido): JsonResponse
    {
        $automatico = $automaticos->programar(
            $request->user('clientes'),
            $assistido->autor($request),
            $codigo,
            (string) $request->validated('valor_alvo'),
        );

        return response()->json(['data' => [
            'valor_alvo' => (string) $automatico->valor_alvo,
            'situacao' => $automatico->situacao->value,
            'criado_em' => $automatico->created_at->copy()->setTimezone(FusoSistema::do_pedido($request->input('fuso_horario')))->toIso8601String(),
        ]]);
    }

    public function destroy(Request $request, string $codigo, EncerramentosAutomaticos $automaticos): Response
    {
        abort_unless($automaticos->cancelar($request->user('clientes'), $codigo, 'Cancelado pelo cliente'), 404, 'Nenhum encerramento automático ativo.');

        return response()->noContent();
    }
}
