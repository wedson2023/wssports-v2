<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

class LoginClienteRequest extends FormRequest
{
    use DadosCliente;

    /**
     * Máximo de tentativas erradas por DDI + telefone + IP dentro da janela.
     */
    private const MAXIMO_TENTATIVAS = 5;

    /**
     * Janela, em segundos, em que as tentativas erradas são contadas.
     */
    private const JANELA_SEGUNDOS = 60;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_ddi_telefone();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ddi' => ['digits_between:1,3'],
            'telefone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            'telefone.required' => 'O telefone é obrigatório.',
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
        return 'login_cliente|'.$this->input('ddi').'.'.$this->input('telefone').'|'.$this->ip();
    }
}
