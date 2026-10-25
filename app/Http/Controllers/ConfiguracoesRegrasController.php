<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesRegrasRequest;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Texto das regras da banca, mostrado no topo da página de regras (spec 006, FR-021b). Um texto
 * único, em HTML limpo do editor desde a spec 021 (FR-018), só do administrador (Admin e
 * Supervisor); cada alteração entra no histórico.
 */
class ConfiguracoesRegrasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => ['regras' => (string) Configuracoes::atual()->regras]]);
    }

    public function update(ConfiguracoesRegrasRequest $request, HistoricoConfiguracoes $historico): JsonResponse
    {
        $configuracoes = $historico->alterar($request->user(), 'regras', Configuracoes::atual(), ['regras' => $request->regras_limpas()]);

        return response()->json(['data' => ['regras' => $configuracoes->regras]]);
    }
}
