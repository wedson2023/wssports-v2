<?php

namespace App\Http\Requests;

use App\Support\CodigoAposta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Consulta pública de várias apostas pelos códigos guardados no aparelho do visitante (FR-056).
 */
class ConsultarApostasRequest extends FormRequest
{
    private const MAXIMO_REQUISICOES = 60;

    private const JANELA_SEGUNDOS = 60;

    private const MAXIMO_CODIGOS = 50;

    public function authorize(): bool
    {
        // rota pública
        return true;
    }

    protected function prepareForValidation(): void
    {
        $chave = 'consulta_apostas|'.$this->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAXIMO_REQUISICOES)) {
            $segundos = RateLimiter::availableIn($chave);

            throw new HttpResponseException(response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429));
        }

        RateLimiter::hit($chave, self::JANELA_SEGUNDOS);

        if (is_array($this->input('codigos'))) {
            $this->merge(['codigos' => array_map(
                fn (mixed $codigo) => is_string($codigo) ? CodigoAposta::normalizar($codigo) : $codigo,
                $this->input('codigos'),
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigos' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_CODIGOS],
            'codigos.*' => ['required', 'string', 'size:'.CodigoAposta::TAMANHO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigos.required' => 'Informe os códigos das apostas.',
            'codigos.array' => 'Os códigos devem ser uma lista.',
            'codigos.min' => 'Informe ao menos um código.',
            'codigos.max' => 'Informe no máximo '.self::MAXIMO_CODIGOS.' códigos por consulta.',
            'codigos.*.required' => 'Código inválido.',
            'codigos.*.string' => 'Código inválido.',
            'codigos.*.size' => 'O código da aposta tem '.CodigoAposta::TAMANHO.' caracteres.',
        ];
    }
}
