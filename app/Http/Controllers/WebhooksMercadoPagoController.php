<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarPagamentoMercadoPago;
use App\Models\NotificacoesMercadoPago;
use App\Models\Saques;
use App\Services\ClienteMercadoPago;
use App\Services\PagamentoSaques;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Avisos do Mercado Pago (spec 007, FR-029, research R-10): grava, confere a assinatura e enfileira;
 * responde 200 sempre, rápido, para o Mercado Pago não repetir à toa.
 */
class WebhooksMercadoPagoController extends Controller
{
    public function store(Request $request, ClienteMercadoPago $mercado_pago): JsonResponse
    {
        // formato novo: {"type": "payment", "data": {"id": "123"}}; antigo: ?topic=payment&id=123
        $tipo = (string) ($request->input('type') ?? $request->query('topic') ?? $request->query('type') ?? '');
        $recurso_id = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('id') ?? '');

        if ($recurso_id === '') {
            return response()->json(['recebido' => true]);
        }

        $notificacao = NotificacoesMercadoPago::create([
            'tipo' => mb_substr($tipo, 0, 40),
            'recurso_id' => mb_substr($recurso_id, 0, 60),
            'request_id' => $request->header('x-request-id'),
            'assinatura_valida' => $mercado_pago->assinatura_valida($request->header('x-signature'), $request->header('x-request-id'), $recurso_id),
            'situacao' => NotificacoesMercadoPago::RECEBIDA,
        ]);

        if (! $notificacao->assinatura_valida) {
            $notificacao->marcar(NotificacoesMercadoPago::IGNORADA, 'assinatura inválida');
        } elseif ($tipo === 'payment') {
            ProcessarPagamentoMercadoPago::dispatch($notificacao->id);
        } else {
            $this->outros_avisos($notificacao);
        }

        return response()->json(['recebido' => true]);
    }

    /**
     * Avisos que não são de pagamento: a documentação do Payouts não nomeia o tipo, então o saque
     * é achado pelo id (research R-13) e a situação é consultada; os demais são ignorados.
     */
    protected function outros_avisos(NotificacoesMercadoPago $notificacao): void
    {
        $saque = Saques::where('pagamento_id', $notificacao->recurso_id)->first();

        if ($saque === null) {
            $notificacao->marcar(NotificacoesMercadoPago::IGNORADA, "tipo {$notificacao->tipo} não tratado");

            return;
        }

        try {
            app(PagamentoSaques::class)->confirmar($saque);
            $notificacao->marcar(NotificacoesMercadoPago::PROCESSADA);
        } catch (Throwable $erro) {
            $notificacao->marcar(NotificacoesMercadoPago::FALHOU, mb_substr($erro->getMessage(), 0, 255));
        }
    }
}
