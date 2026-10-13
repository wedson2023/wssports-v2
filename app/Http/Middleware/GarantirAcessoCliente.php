<?php

namespace App\Http\Middleware;

use App\Models\ClientesAcessosAssistidos;
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
            // desativado com a sessão aberta: a tela de créditos mostra "Pagamento pendente ou
            // conta desativada." (spec 007, FR-005); excluído segue com a mensagem genérica
            $mensagem = $cliente !== null && ! $cliente->trashed()
                ? 'Pagamento pendente ou conta desativada.'
                : 'Cliente sem permissão de acesso.';

            return response()->json(['message' => $mensagem], 403);
        }

        // token do acesso assistido pelo painel (spec 007, US7): só vale com o acesso em aberto
        $acesso_id = auth('clientes')->payload()->get('acesso_assistido_id');

        if ($acesso_id !== null) {
            $acesso = ClientesAcessosAssistidos::find($acesso_id);

            if ($acesso === null || ! $acesso->ativo()) {
                if ($acesso !== null && $acesso->encerrado_em === null) {
                    // venceu sem ninguém encerrar: o fim fica registrado (FR-048)
                    $acesso->forceFill(['encerrado_em' => $acesso->expira_em ?? now()])->save();
                }

                return response()->json(['message' => 'Acesso assistido encerrado.'], 401);
            }
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
