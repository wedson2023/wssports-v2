<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia usuários inativos ou excluídos, mesmo que tenham um token ainda válido.
 */
class GarantirAcesso
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->pode_acessar()) {
            return response()->json(['message' => 'Usuário sem permissão de acesso.'], 403);
        }

        return $next($request);
    }
}
