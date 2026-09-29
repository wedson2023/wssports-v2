<?php

namespace App\Http\Requests;

use App\Enums\Genero;
use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Cadastro público do cliente (área externa do site).
 */
class StoreClientesRequest extends FormRequest
{
    use DadosCliente;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Máximo de cadastros por IP dentro da janela (FR-001a).
     */
    private const MAXIMO_TENTATIVAS = 5;

    private const JANELA_SEGUNDOS = 60;

    protected function prepareForValidation(): void
    {
        $this->garantir_limite_tentativas();
        $this->normalizar_dados_cliente();

        $this->merge([
            'ddi' => $this->input('ddi') ?: '55',
            'aceita_promocao' => $this->has('aceita_promocao') ? $this->input('aceita_promocao') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'ddi' => ['digits_between:1,3'],
            'telefone' => ['required', ...$this->regras_telefone((string) $this->input('ddi'))],
            'email' => $this->regras_email(),
            'cpf' => $this->regras_cpf(),
            'password' => ['required', 'confirmed', $this->regra_senha()],
            'data_nascimento' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'genero' => ['required', Rule::enum(Genero::class)],
            'codigo_afiliado' => ['nullable', 'string', 'max:50'],
            'aceita_promocao' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensagens_dados_cliente();
    }

    /**
     * Conta toda tentativa de cadastro do IP e interrompe com 429 acima do limite.
     */
    private function garantir_limite_tentativas(): void
    {
        $chave = 'cadastro_cliente|'.$this->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_TENTATIVAS)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);
    }
}
