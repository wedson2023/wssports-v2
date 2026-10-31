<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarEmailClienteRequest;
use App\Http\Requests\ReenviarConfirmacaoEmailRequest;
use App\Http\Resources\ClientesResource;
use App\Models\Clientes;
use App\Services\ConfirmacaoCadastro;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Confirmação de e-mail do cadastro (spec 025, US8): confirma pelo link e reenvia um link novo, com a
 * mesma resposta exista ou não o cadastro.
 */
class AreaClienteConfirmacaoEmailController extends Controller
{
    private const INTERVALO_REENVIO_SEGUNDOS = 60;

    /**
     * Confirma e já entra na conta; cliente bloqueado no painel confirma, mas continua sem acesso.
     */
    public function confirmar(ConfirmarEmailClienteRequest $request, ConfirmacaoCadastro $confirmacao): JsonResponse
    {
        $cliente = $confirmacao->confirmar($request->input('token'));

        if ($cliente === null) {
            return response()->json(['message' => 'Link expirado. Peça um novo link.'], 422);
        }

        if (! $cliente->pode_acessar()) {
            return response()->json(['message' => 'E-mail confirmado.']);
        }

        return response()->json([
            'cliente' => new ClientesResource($cliente),
            'token' => auth('clientes')->login($cliente),
            'tipo' => 'bearer',
            'expira_em' => auth('clientes')->factory()->getTTL() * 60,
        ]);
    }

    public function reenviar(ReenviarConfirmacaoEmailRequest $request, ConfirmacaoCadastro $confirmacao): JsonResponse
    {
        $chave = 'reenviar_confirmacao|'.$request->input('ddi').'.'.$request->input('telefone');

        if (RateLimiter::tooManyAttempts($chave, 1)) {
            $segundos = RateLimiter::availableIn($chave);

            return response()->json(['message' => "Muitas tentativas. Tente novamente em {$segundos} segundos."], 429);
        }

        RateLimiter::hit($chave, self::INTERVALO_REENVIO_SEGUNDOS);

        $cliente = Clientes::where('ddi', $request->input('ddi'))->where('telefone', $request->input('telefone'))->first();

        if ($cliente?->ativo && $cliente->confirmacao_pendente) {
            $confirmacao->enviar_link($cliente);
        }

        return response()->json(['message' => 'Se houver cadastro pendente, enviaremos um novo link.']);
    }
}
