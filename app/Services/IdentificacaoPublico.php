<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Clientes;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Throwable;

/**
 * Descobre quem está vendo a listagem pública pelo token (opcional). Tenta o guard dos clientes
 * e depois o do painel; os dois usam JWT com lock_subject, então um token nunca vale no guard do
 * outro. Aplica as mesmas regras de acesso dos middlewares das áreas autenticadas. Token inválido,
 * expirado ou de quem não pode acessar vira visitante, com token_recusado = true.
 */
class IdentificacaoPublico
{
    public function identificar(Request $request): Publico
    {
        if ($request->bearerToken() === null) {
            return Publico::visitante();
        }

        $cliente = $this->cliente();

        if ($cliente !== null) {
            return new Publico(Publico::CLIENTE, cliente: $cliente);
        }

        $usuario = $this->usuario();

        if ($usuario !== null) {
            $tipo = $usuario->funcao() === Funcao::Vendedor ? Publico::VENDEDOR : Publico::GESTOR;

            return new Publico($tipo, usuario: $usuario);
        }

        return Publico::visitante(token_recusado: true);
    }

    /**
     * Cliente do token, se ativo e com o token emitido depois da data de corte (mesma regra do
     * middleware GarantirAcessoCliente).
     */
    private function cliente(): ?Clientes
    {
        try {
            $cliente = auth('clientes')->user();

            if (! $cliente instanceof Clientes || ! $cliente->pode_acessar()) {
                return null;
            }

            if ($cliente->tokens_validos_desde !== null
                && (int) auth('clientes')->payload()->get('iat') < $cliente->tokens_validos_desde->getTimestamp()) {
                return null;
            }

            return $cliente;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Usuário do painel do token, se puder acessar (mesma regra do middleware GarantirAcesso).
     */
    private function usuario(): ?Usuarios
    {
        try {
            $usuario = auth('api')->user();

            return $usuario instanceof Usuarios && $usuario->pode_acessar() ? $usuario : null;
        } catch (Throwable) {
            return null;
        }
    }
}
