<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia clientes inativos e tokens emitidos antes da data de corte do cliente
 * (troca ou recuperação de senha, desativação ou exclusão).
 */
class GarantirAcessoCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = $request->user('clientes');

        if (! $cliente?->pode_acessar()) {
            return response()->json(['message' => 'Cliente sem permissão de acesso.'], 403);
        }

        if ($cliente->tokens_validos_desde !== null) {
            $emitido_em = (int) auth('clientes')->payload()->get('iat');

            if ($emitido_em < $cliente->tokens_validos_desde->getTimestamp()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        }

        return $next($request);
    }
}
