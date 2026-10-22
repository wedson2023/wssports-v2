<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ApostasFiltrosRequest;
use App\Services\PremiadosApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Premiados do período para a imagem (spec 018, contracts/api.md §3.1): Gerente para cima; o
 * Vendedor não divulga premiados, mesmo com a permissão de exportar (FR-020).
 */
class ApostasPremiadosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly PremiadosApostas $premiados) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.exportar'),
        ];
    }

    public function __invoke(ApostasFiltrosRequest $request): JsonResponse
    {
        abort_if($request->user()->funcao() === Funcao::Vendedor, 403, 'Você não tem permissão para esta ação.');

        return response()->json($this->premiados->premiados($request->user(), $request->filtros()));
    }
}
