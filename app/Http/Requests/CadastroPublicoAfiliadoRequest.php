<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cadastro público do afiliado em /cadastro-afiliado (contracts/api §1.1). Situação, gerente e
 * regra de comissão nunca vêm deste formulário (FR-011).
 */
class CadastroPublicoAfiliadoRequest extends FormRequest
{
    use DadosAfiliado;

    /**
     * Máximo de cadastros por IP dentro da janela, como no cadastro público de clientes (FR-012).
     */
    private const MAXIMO_TENTATIVAS = 5;

    private const JANELA_SEGUNDOS = 60;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->garantir_limite_tentativas();
        $this->normalizar_dados_afiliado();

        $this->merge(['ddi' => $this->input('ddi') ?: '55']);
    }

    /**
     * Só os campos abaixo chegam ao validated(): o resto do corpo é ignorado.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->regras_dados_afiliado('required'),
            'login' => $this->regras_login(),
            'password' => ['required', 'confirmed', $this->regra_senha()],
            // perfil na rede social (spec 022, FR-016)
            ...$this->regras_perfil_afiliado('required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensagens_dados_afiliado();
    }

    /**
     * Conta toda tentativa de cadastro do IP e interrompe com 429 acima do limite.
     */
    private function garantir_limite_tentativas(): void
    {
        $chave = 'cadastro_afiliado|'.$this->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_TENTATIVAS)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);
    }
}
