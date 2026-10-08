<?php

namespace App\Http\Requests;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Aposta do visitante (gera o código): só pré-jogo e com limite de tentativas por IP (R-10).
 */
class ApostaVisitanteRequest extends ApostasRequest
{
    private const MAXIMO_TENTATIVAS = 10;

    private const JANELA_SEGUNDOS = 60;

    protected function prepareForValidation(): void
    {
        $chave = 'aposta_visitante|'.$this->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_TENTATIVAS)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);

        parent::prepareForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'palpites.*.confrontos_ao_vivo_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'palpites.*.confrontos_ao_vivo_id.prohibited' => 'Para apostar no ao vivo é preciso fazer login.',
        ];
    }
}
