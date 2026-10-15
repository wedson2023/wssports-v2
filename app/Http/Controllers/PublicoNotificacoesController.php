<?php

namespace App\Http\Controllers;

use App\Services\NotificacoesPush;
use Illuminate\Http\JsonResponse;

/**
 * Configuração pública das notificações push (spec 010): a chave pública VAPID usada pelo navegador
 * para inscrever o aparelho.
 */
class PublicoNotificacoesController extends Controller
{
    public function configuracao(): JsonResponse
    {
        $habilitado = NotificacoesPush::habilitado();

        return response()->json(['data' => [
            'chave_publica' => $habilitado ? config('services.notificacoes_push.chave_publica') : null,
            'habilitado' => $habilitado,
        ]]);
    }
}
