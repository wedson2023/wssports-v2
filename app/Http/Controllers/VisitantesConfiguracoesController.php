<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\VisitantesConfiguracoesRequest;
use App\Models\VisitantesConfiguracoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações de quem não está logado (registro único). Só Admin e Supervisor.
 */
class VisitantesConfiguracoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('visitantes_configuracoes.editar'),
        ];
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => VisitantesConfiguracoes::atual()->only(VisitantesConfiguracoes::CAMPOS)]);
    }

    public function update(VisitantesConfiguracoesRequest $request): JsonResponse
    {
        $configuracoes = VisitantesConfiguracoes::atual();
        $configuracoes->update([
            ...$request->validated(),
            'esportes_permitidos' => array_values($request->validated('esportes_permitidos')),
        ]);

        return response()->json(['data' => $configuracoes->only(VisitantesConfiguracoes::CAMPOS)]);
    }
}
