<?php

namespace App\Http\Controllers;

use App\Events\CodigoRecuperacaoGerado;
use App\Http\Requests\RecuperarSenhaClienteRequest;
use App\Http\Requests\RedefinirSenhaClienteRequest;
use App\Models\Clientes;
use App\Models\ClientesCodigosRecuperacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AreaClienteRecuperacaoSenhaController extends Controller
{
    private const VALIDADE_MINUTOS = 15;

    private const MAXIMO_TENTATIVAS_CODIGO = 5;

    private const INTERVALO_PEDIDOS_SEGUNDOS = 60;

    /**
     * Gera e envia um código de recuperação. A resposta é sempre a mesma, para não revelar
     * se o telefone pertence a um cliente.
     */
    public function solicitar(RecuperarSenhaClienteRequest $request): JsonResponse
    {
        $chave = 'recuperar_senha|'.$request->input('ddi').'.'.$request->input('telefone');

        if (RateLimiter::tooManyAttempts($chave, 1)) {
            $segundos = RateLimiter::availableIn($chave);

            return response()->json(['message' => "Muitas tentativas. Tente novamente em {$segundos} segundos."], 429);
        }

        RateLimiter::hit($chave, self::INTERVALO_PEDIDOS_SEGUNDOS);

        $cliente = $this->cliente_do_telefone($request->input('ddi'), $request->input('telefone'));

        if ($cliente?->pode_acessar()) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::transaction(function () use ($cliente, $codigo) {
                // um novo pedido invalida os códigos anteriores ainda válidos
                $cliente->codigos_recuperacao()
                    ->whereNull('usado_em')
                    ->whereNull('invalidado_em')
                    ->update(['invalidado_em' => now()]);

                $cliente->codigos_recuperacao()->create([
                    'codigo' => Hash::make($codigo),
                    'expira_em' => now()->addMinutes(self::VALIDADE_MINUTOS),
                ]);

                CodigoRecuperacaoGerado::dispatch($cliente, $codigo);
            });
        }

        return response()->json(['message' => 'Se o telefone estiver cadastrado, enviaremos um código.']);
    }

    /**
     * Troca a senha com um código válido e faz todos os tokens anteriores deixarem de valer.
     */
    public function redefinir(RedefinirSenhaClienteRequest $request): JsonResponse|Response
    {
        $cliente = $this->cliente_do_telefone($request->input('ddi'), $request->input('telefone'));

        $codigo = $cliente?->pode_acessar()
            ? $cliente->codigos_recuperacao()->latest('id')->first()
            : null;

        if (! $codigo?->valido()) {
            return $this->codigo_invalido();
        }

        if (! Hash::check($request->input('codigo'), $codigo->codigo)) {
            $codigo->tentativas++;

            if ($codigo->tentativas >= self::MAXIMO_TENTATIVAS_CODIGO) {
                $codigo->invalidado_em = now();
            }

            $codigo->save();

            return $this->codigo_invalido();
        }

        DB::transaction(function () use ($cliente, $codigo, $request) {
            $cliente->update(['password' => $request->input('password')]);
            $codigo->update(['usado_em' => now()]);
            $cliente->invalidar_tokens();
        });

        return response()->noContent();
    }

    private function cliente_do_telefone(string $ddi, string $telefone): ?Clientes
    {
        return Clientes::where('ddi', $ddi)->where('telefone', $telefone)->first();
    }

    private function codigo_invalido(): JsonResponse
    {
        return response()->json(['message' => 'Código inválido ou expirado.'], 422);
    }
}
