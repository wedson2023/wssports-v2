<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\ClientesAcessosAssistidos;
use App\Models\Configuracoes;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Acesso de admin e supervisor à conta de um cliente (spec 007, US7, research R-16): o painel gera
 * um código de uso único (2 minutos) e o site o troca por um token do cliente com o autor nas
 * claims, sem usar a senha nem a sessão do cliente.
 */
class AcessoAssistido
{
    private const MINUTOS_CODIGO = 2;

    /**
     * @return array{url: string, expira_em: mixed}
     */
    public function gerar(Usuarios $usuario, Clientes $cliente, ?string $ip): array
    {
        $codigo = Str::random(40);

        $acesso = ClientesAcessosAssistidos::create([
            'clientes_id' => $cliente->id,
            'usuarios_id' => $usuario->id,
            'codigo_hash' => hash('sha256', $codigo),
            'codigo_expira_em' => now()->addMinutes(self::MINUTOS_CODIGO),
            'ip' => $ip,
        ]);

        return ['url' => url('/minha-conta?acesso='.$codigo), 'expira_em' => $acesso->codigo_expira_em];
    }

    /**
     * Troca o código por um token do cliente com as claims do acesso.
     *
     * @return array{token: string, tipo: string, expira_em: int, cliente: array{nome: string}, usuario: string}
     */
    public function iniciar(string $codigo, ?string $ip): array
    {
        $acesso = ClientesAcessosAssistidos::with(['cliente', 'usuario'])
            ->where('codigo_hash', hash('sha256', $codigo))
            ->whereNull('iniciado_em')
            ->where('codigo_expira_em', '>', now())
            ->first();

        if ($acesso === null || $acesso->cliente === null) {
            throw ValidationException::withMessages(['codigo' => 'Acesso expirado. Gere um novo pelo painel.']);
        }

        $minutos = Configuracoes::atual()->minutos_acesso_assistido;
        $acesso->forceFill(['iniciado_em' => now(), 'expira_em' => now()->addMinutes($minutos), 'ip' => $ip ?? $acesso->ip])->save();

        $token = auth('clientes')
            ->setTTL($minutos)
            ->claims(['acesso_assistido_id' => $acesso->id, 'assistido_por' => $acesso->usuarios_id])
            ->login($acesso->cliente);

        return [
            'token' => $token,
            'tipo' => 'bearer',
            'expira_em' => $minutos * 60,
            'cliente' => ['nome' => $acesso->cliente->nome],
            'usuario' => $acesso->usuario?->nome,
        ];
    }

    /**
     * Acesso do token da requisição (null quando é o próprio cliente).
     */
    public function acesso(Request $request): ?ClientesAcessosAssistidos
    {
        $id = $this->claim('acesso_assistido_id');

        return $id ? ClientesAcessosAssistidos::with('usuario')->find($id) : null;
    }

    /**
     * Usuário do painel que está operando a conta (autor das movimentações).
     */
    public function autor(Request $request): ?Usuarios
    {
        return $this->acesso($request)?->usuario;
    }

    public function encerrar(Request $request): void
    {
        $this->acesso($request)?->forceFill(['encerrado_em' => now()])->save();
        auth('clientes')->logout();
    }

    private function claim(string $nome): mixed
    {
        try {
            return auth('clientes')->payload()->get($nome);
        } catch (Throwable) {
            return null;
        }
    }
}
