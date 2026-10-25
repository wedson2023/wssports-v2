<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Exceptions\RoboIndisponivelException;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Services\RoboWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Robô do WhatsApp na aba Integrações (spec 021, US5): Admin e Supervisor veem a situação; só o
 * Admin conecta e desconecta (Clarification 1, research R-13).
 */
class RoboWhatsappController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private RoboWhatsapp $robo) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('configuracoes.editar', only: ['show']),
            self::permissao_cliente('robo_whatsapp.conectar', only: ['conectar', 'destroy']),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->robo->situacao(),
            'pode_alterar' => Funcao::usuario_pode($request->user(), 'robo_whatsapp.conectar'),
        ]]);
    }

    public function conectar(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->robo->conectar($request->user())]);
        } catch (RoboIndisponivelException $erro) {
            return response()->json(['message' => $erro->getMessage()], 503);
        }
    }

    public function destroy(Request $request): Response|JsonResponse
    {
        try {
            $this->robo->desconectar($request->user());
        } catch (RoboIndisponivelException $erro) {
            return response()->json(['message' => $erro->getMessage()], 503);
        }

        return response()->noContent();
    }
}
