<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Clientes;
use App\Services\AlcanceClientes;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Alcance dos clientes nos controllers do painel (spec 019, FR-003a): só os clientes da rede do
 * usuário, pela regra de AlcanceClientes; fora dela, o cliente é tratado como inexistente.
 */
trait GarantirAlcanceCliente
{
    /**
     * Middleware de controller que confere o cliente da rota ({cliente}) na rede do usuário.
     */
    protected static function alcance_cliente(): Middleware
    {
        return new Middleware(function (Request $request, Closure $next) {
            $cliente = $request->route('cliente');

            if ($cliente instanceof Clientes) {
                app(AlcanceClientes::class)->garantir($request->user(), $cliente);
            }

            return $next($request);
        });
    }

    private function garantir_cliente(Request $request, Clientes $cliente): void
    {
        app(AlcanceClientes::class)->garantir($request->user(), $cliente);
    }

    private function aplicar_alcance_cliente(Request $request, Builder|EloquentBuilder $consulta, string $coluna = 'clientes.id'): Builder|EloquentBuilder
    {
        return app(AlcanceClientes::class)->aplicar($consulta, $request->user(), $coluna);
    }
}
