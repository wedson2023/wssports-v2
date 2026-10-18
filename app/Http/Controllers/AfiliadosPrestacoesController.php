<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirAlcanceAfiliado;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\PrestacaoContasAfiliadoRequest;
use App\Http\Resources\AfiliadosPrestacoesContasResource;
use App\Models\Afiliados;
use App\Services\PrestacaoContasAfiliados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Prestações de contas do afiliado no painel (spec 012, US6): histórico e nova prestação. Afiliado
 * na lixeira não é encontrado pela rota: é preciso restaurar antes.
 */
class AfiliadosPrestacoesController extends Controller implements HasMiddleware
{
    use GarantirAlcanceAfiliado, GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('afiliados.consultar', only: ['index']),
            self::permissao_cliente('afiliados.prestar_contas', only: ['store']),
        ];
    }

    public function index(Request $request, Afiliados $afiliado): AnonymousResourceCollection
    {
        $this->garantir_alcance($request, $afiliado);

        $por_pagina = $request->validate(
            ['por_pagina' => ['nullable', 'integer', 'between:1,100']],
            ['por_pagina.between' => 'O campo por página deve estar entre 1 e 100.', 'por_pagina.integer' => 'O campo por página deve ser um número inteiro.'],
        )['por_pagina'] ?? 20;

        $prestacoes = $afiliado->prestacoes()->with('usuario')->latest('periodo_fim')->latest('id')->paginate($por_pagina)->withQueryString();

        return AfiliadosPrestacoesContasResource::collection($prestacoes);
    }

    public function store(PrestacaoContasAfiliadoRequest $request, Afiliados $afiliado, PrestacaoContasAfiliados $prestacoes): JsonResponse
    {
        $this->garantir_alcance($request, $afiliado);

        $prestacao = $prestacoes->prestar($afiliado, $request->user(), $request->validated('observacao'));

        return (new AfiliadosPrestacoesContasResource($prestacao->load('usuario')))->response()->setStatusCode(201);
    }
}
