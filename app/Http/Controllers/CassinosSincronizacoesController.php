<?php

namespace App\Http\Controllers;

use App\Enums\OrigemSincronizacaoCassino;
use App\Enums\SituacaoSincronizacaoCassino;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\CassinosSincronizacoesResource;
use App\Jobs\SincronizarCatalogoCassino;
use App\Models\CassinosSincronizacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Cargas do catálogo do cassino no painel (spec 011, FR-001, FR-005): resumo de cada carga e carga
 * sob demanda (na fila).
 */
class CassinosSincronizacoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_jogos.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $por_pagina = min(max((int) $request->query('por_pagina', 20), 1), 100);

        return CassinosSincronizacoesResource::collection(
            CassinosSincronizacoes::with('usuario')->orderByDesc('id')->paginate($por_pagina, ['*'], 'pagina')->withQueryString(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $em_andamento = CassinosSincronizacoes::where('situacao', SituacaoSincronizacaoCassino::EmAndamento)
            ->where('iniciada_em', '>', now()->subMinutes(30))
            ->exists();

        abort_if($em_andamento, 409, 'Já existe uma atualização do catálogo em andamento.');

        SincronizarCatalogoCassino::dispatch($request->user()->id);

        return response()->json([
            'message' => 'Atualização do catálogo solicitada.',
            'origem' => OrigemSincronizacaoCassino::Painel,
            'situacao' => SituacaoSincronizacaoCassino::EmAndamento,
        ], 202);
    }
}
