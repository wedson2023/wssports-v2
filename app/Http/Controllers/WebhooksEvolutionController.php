<?php

namespace App\Http\Controllers;

use App\Services\ClienteEvolution;
use App\Services\RoboWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos automáticos da Evolution API (spec 021, FR-026a, research R-11): conferidos pelo token
 * secreto do cabeçalho x-webhook-token e pela instância da banca; só a mudança de conexão é tratada.
 * Responde rápido para a Evolution não repetir à toa.
 */
class WebhooksEvolutionController extends Controller
{
    public function store(Request $request, ClienteEvolution $evolution, RoboWhatsapp $robo): JsonResponse
    {
        $token = (string) config('services.evolution.webhook_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('x-webhook-token'))) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        if ((string) $request->input('instance') !== $evolution->instancia()) {
            return response()->json(['message' => 'Instância não encontrada.'], 404);
        }

        $robo->tratar_aviso($request->all());

        return response()->json(['recebido' => true]);
    }
}
