<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia afiliados sem acesso (em análise, recusados, inativos ou excluídos) e tokens emitidos
 * antes da data de corte do afiliado (inativação, exclusão ou troca de senha). Spec 012, R-03.
 */
class GarantirAcessoAfiliado
{
    public function handle(Request $request, Closure $next): Response
    {
        $afiliado = $request->user('afiliados');

        if (! $afiliado?->pode_acessar()) {
            $mensagem = $afiliado !== null && ! $afiliado->trashed()
                ? $afiliado->situacao->mensagem_sem_acesso()
                : 'Afiliado sem permissão de acesso.';

            return response()->json(['message' => $mensagem], 403);
        }

        if ($afiliado->tokens_validos_desde !== null) {
            $emitido_em = (int) auth('afiliados')->payload()->get('iat');

            if ($emitido_em < $afiliado->tokens_validos_desde->getTimestamp()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        }

        return $next($request);
    }
}
