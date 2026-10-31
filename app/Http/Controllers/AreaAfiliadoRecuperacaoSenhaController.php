<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecuperarSenhaAfiliadoRequest;
use App\Http\Requests\RedefinirSenhaAfiliadoRequest;
use App\Models\Afiliados;
use App\Services\DisparoMensagens;
use App\Services\TextosMensagens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * "Esqueci minha senha" do afiliado, por código enviado pelo canal configurado (spec 012, FR-021a;
 * spec 025), no padrão da recuperação do cliente.
 */
class AreaAfiliadoRecuperacaoSenhaController extends Controller
{
    private const VALIDADE_MINUTOS = 15;

    private const MAXIMO_TENTATIVAS_CODIGO = 5;

    private const INTERVALO_PEDIDOS_SEGUNDOS = 60;

    /**
     * Gera e envia um código de recuperação. A resposta é sempre a mesma, para não revelar se o
     * login ou o e-mail pertence a um afiliado.
     */
    public function solicitar(RecuperarSenhaAfiliadoRequest $request, DisparoMensagens $disparo, TextosMensagens $textos): JsonResponse
    {
        $chave = 'recuperar_senha_afiliado|'.$request->input('identificacao');

        if (RateLimiter::tooManyAttempts($chave, 1)) {
            $segundos = RateLimiter::availableIn($chave);

            return response()->json(['message' => "Muitas tentativas. Tente novamente em {$segundos} segundos."], 429);
        }

        RateLimiter::hit($chave, self::INTERVALO_PEDIDOS_SEGUNDOS);

        $afiliado = $this->afiliado_da_identificacao($request->input('identificacao'));

        // o canal é o configurado, com reserva: basta ter e-mail ou telefone (spec 025, FR-008)
        if ($afiliado?->pode_acessar() && ($afiliado->email !== null || $afiliado->telefone !== null)) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $registro = DB::transaction(function () use ($afiliado, $codigo) {
                // um novo pedido invalida os códigos anteriores ainda válidos
                $afiliado->codigos_recuperacao()
                    ->whereNull('usado_em')
                    ->whereNull('invalidado_em')
                    ->update(['invalidado_em' => now()]);

                return $afiliado->codigos_recuperacao()->create([
                    'codigo' => Hash::make($codigo),
                    'expira_em' => now()->addMinutes(self::VALIDADE_MINUTOS),
                ]);
            });

            try {
                $disparo->enviar($textos->codigo_recuperacao_afiliado($afiliado, $codigo, $registro->id, self::VALIDADE_MINUTOS));
            } catch (Throwable $erro) {
                Log::error('Falha ao enviar o código de recuperação do afiliado.', ['afiliados_id' => $afiliado->id, 'erro' => $erro::class]);
            }
        }

        return response()->json(['message' => 'Se o cadastro existir, enviaremos um código.']);
    }

    /**
     * Troca a senha com um código válido e faz todos os tokens anteriores deixarem de valer.
     */
    public function redefinir(RedefinirSenhaAfiliadoRequest $request): JsonResponse|Response
    {
        $afiliado = $this->afiliado_da_identificacao($request->input('identificacao'));

        $codigo = $afiliado?->pode_acessar()
            ? $afiliado->codigos_recuperacao()->latest('id')->first()
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

        DB::transaction(function () use ($afiliado, $codigo, $request) {
            $afiliado->update(['password' => $request->input('password')]);
            $codigo->update(['usado_em' => now()]);
            $afiliado->invalidar_tokens();
        });

        return response()->noContent();
    }

    /**
     * Afiliado pelo login ou pelo e-mail, sem diferenciar maiúsculas (a identificação já chega em
     * minúsculas).
     */
    private function afiliado_da_identificacao(string $identificacao): ?Afiliados
    {
        return Afiliados::where(fn ($consulta) => $consulta
            ->whereRaw('LOWER(login) = ?', [$identificacao])
            ->orWhere('email', $identificacao))
            ->first();
    }

    private function codigo_invalido(): JsonResponse
    {
        return response()->json(['message' => 'Código inválido ou expirado.'], 422);
    }
}
