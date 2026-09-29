<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Funcao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

trait GarantirPermissaoCliente
{
    /**
     * Middleware de controller que exige a permissão direta E que a função do usuário possa
     * usá-la (Funcao::usuario_pode), ex.: um Gerente com clientes.excluir continua recusado.
     *
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    protected static function permissao_cliente(string $permissao, array $only = [], array $except = []): Middleware
    {
        $middleware = new Middleware(function (Request $request, Closure $next) use ($permissao) {
            if (! Funcao::usuario_pode($request->user(), $permissao)) {
                return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
            }

            return $next($request);
        });

        if ($only !== []) {
            $middleware->only($only);
        }

        if ($except !== []) {
            $middleware->except($except);
        }

        return $middleware;
    }
}
