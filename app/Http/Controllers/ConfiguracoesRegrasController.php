<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesRegrasRequest;
use App\Models\Configuracoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Texto das regras da banca, mostrado no topo da página de regras (spec 006, FR-021b). Um texto
 * único, só do administrador (Admin e Supervisor).
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

    public function update(ConfiguracoesRegrasRequest $request): JsonResponse
    {
        $configuracoes = Configuracoes::atual();
        // texto vazio chega como null (middleware de strings vazias): grava "" para esconder o
        // bloco sem voltar ao texto padrão
        $configuracoes->regras = $request->validated('regras') ?? '';
        $configuracoes->save();

        return response()->json(['data' => ['regras' => $configuracoes->regras]]);
    }
}
