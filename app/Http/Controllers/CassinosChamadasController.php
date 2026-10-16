<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ListagemCassinosChamadasRequest;
use App\Http\Resources\CassinosChamadasResource;
use App\Models\CassinosChamadas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Registro das chamadas do provedor do cassino no painel (spec 011, FR-026, FR-030): conferir os
 * formatos reais e ver as recusadas (ex.: ação desconhecida a cadastrar).
 */
class CassinosChamadasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_rodadas.listar')];
    }

    public function index(ListagemCassinosChamadasRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $chamadas = CassinosChamadas::query()
            ->when($filtros['resultado'] ?? null, fn (Builder $c, $resultado) => $c->where('resultado', $resultado))
            ->when($filtros['comando'] ?? null, fn (Builder $c, $comando) => $c->where('comando', $comando))
            ->when($filtros['login'] ?? null, fn (Builder $c, $login) => $c->where('login', $login))
            ->when($filtros['trade_id'] ?? null, fn (Builder $c, $trade_id) => $c->where('trade_id', $trade_id))
            ->when($filtros['data_inicial'] ?? null, fn (Builder $c, $data) => $c->where('created_at', '>=', $data.' 00:00:00'))
            ->when($filtros['data_final'] ?? null, fn (Builder $c, $data) => $c->where('created_at', '<=', $data.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 20, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        return CassinosChamadasResource::collection($chamadas);
    }
}
