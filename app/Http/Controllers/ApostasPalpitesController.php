<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\ComprovanteApostaResource;
use App\Services\EdicaoApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Edição da aposta pelo painel: cancelar ou restaurar um palpite, com o prêmio recalculado.
 */
class ApostasPalpitesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private EdicaoApostas $edicao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.editar'),
        ];
    }

    public function cancelar(Request $request, string $codigo, int $palpite): JsonResponse
    {
        $aposta = $this->edicao->cancelar_palpite($request->user(), $codigo, $palpite, (string) $request->ip(), $request->userAgent());

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), mostrar_comissao: true)]);
    }

    public function restaurar(Request $request, string $codigo, int $palpite): JsonResponse
    {
        $aposta = $this->edicao->restaurar_palpite($request->user(), $codigo, $palpite, (string) $request->ip(), $request->userAgent());

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), mostrar_comissao: true)]);
    }
}
