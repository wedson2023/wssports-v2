<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RelatorioCassinoRequest;
use App\Services\RelatorioCassino;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Relatório do cassino no painel (spec 011, FR-032): movimento do período por jogo, provedor ou canal,
 * com GGR e comissão. Sem bloqueio por GGR (FR-031).
 */
class CassinosRodadasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_rodadas.listar')];
    }

    public function relatorio(RelatorioCassinoRequest $request, RelatorioCassino $relatorio): JsonResponse
    {
        ['resumo' => $resumo, 'itens' => $itens] = $relatorio->gerar($request->validated());

        return response()->json([
            'resumo' => $resumo,
            'data' => $itens->items(),
            'links' => ['first' => $itens->url(1), 'last' => $itens->url($itens->lastPage()), 'prev' => $itens->previousPageUrl(), 'next' => $itens->nextPageUrl()],
            'meta' => ['current_page' => $itens->currentPage(), 'last_page' => $itens->lastPage(), 'per_page' => $itens->perPage(), 'total' => $itens->total()],
        ]);
    }
}
