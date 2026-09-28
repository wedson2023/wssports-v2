<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

class LoginRequest extends FormRequest
{
    /**
     * Máximo de tentativas erradas por login + IP dentro da janela.
     */
    private const MAXIMO_TENTATIVAS = 5;

    /**
     * Janela, em segundos, em que as tentativas erradas são contadas.
     */
    private const JANELA_SEGUNDOS = 60;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->login)) {
            $this->merge(['login' => trim($this->login)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'O login é obrigatório.',
            'login.string' => 'O login deve ser um texto.',
            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto.',
        ];
    }

    /**
     * Interrompe com 429 quando o limite de tentativas erradas foi atingido.
     */
    public function garantir_limite_tentativas(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chave_limite(), self::MAXIMO_TENTATIVAS)) {
            return;
        }

        $segundos = RateLimiter::availableIn($this->chave_limite());

        throw new HttpResponseException(response()->json([
            'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
        ], 429));
    }

    public function registrar_tentativa_falha(): void
    {
        RateLimiter::hit($this->chave_limite(), self::JANELA_SEGUNDOS);
    }

    public function limpar_tentativas(): void
    {
        RateLimiter::clear($this->chave_limite());
    }

    private function chave_limite(): string
    {
        return mb_strtolower((string) $this->input('login')).'|'.$this->ip();
    }
}
