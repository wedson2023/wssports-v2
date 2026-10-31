<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\ClientesConfirmacoesEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Confirmação de cadastro por e-mail (spec 025, US8, research R-12): link de uso único, válido por 24
 * horas; só o hash do token fica no banco, e um link novo invalida os anteriores.
 */
class ConfirmacaoCadastro
{
    private const VALIDADE_HORAS = 24;

    public function __construct(private DisparoMensagens $disparo, private TextosMensagens $textos) {}

    public function enviar_link(Clientes $cliente): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($cliente, $token) {
            $this->invalidar($cliente);

            $confirmacao = ClientesConfirmacoesEmail::create([
                'clientes_id' => $cliente->id,
                'token' => hash('sha256', $token),
                'expira_em' => now()->addHours(self::VALIDADE_HORAS),
            ]);

            $this->disparo->enviar($this->textos->confirmacao_cadastro($cliente, url('/confirmar-email?token='.$token), $confirmacao->id));
        });
    }

    /**
     * Confirma pelo token do link; null quando o link é inválido, já foi usado ou venceu.
     */
    public function confirmar(string $token): ?Clientes
    {
        return DB::transaction(function () use ($token) {
            $confirmacao = ClientesConfirmacoesEmail::with('cliente')
                ->where('token', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $confirmacao?->valido() || $confirmacao->cliente === null) {
                return null;
            }

            $confirmacao->update(['usado_em' => now()]);
            $this->marcar_confirmado($confirmacao->cliente);

            return $confirmacao->cliente;
        });
    }

    /**
     * Confirmação manual pelo painel (FR-033): os links abertos deixam de valer.
     */
    public function confirmar_manualmente(Clientes $cliente): void
    {
        DB::transaction(function () use ($cliente) {
            $this->invalidar($cliente);
            $this->marcar_confirmado($cliente);
        });
    }

    private function marcar_confirmado(Clientes $cliente): void
    {
        $cliente->forceFill(['confirmacao_pendente' => false, 'email_confirmado_em' => now()])->save();
    }

    private function invalidar(Clientes $cliente): void
    {
        ClientesConfirmacoesEmail::where('clientes_id', $cliente->id)
            ->whereNull('usado_em')
            ->whereNull('invalidado_em')
            ->update(['invalidado_em' => now()]);
    }
}
