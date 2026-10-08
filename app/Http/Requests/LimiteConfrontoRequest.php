<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Novo limite de valor apostado de um confronto (pré-jogo ou ao vivo).
 */
class LimiteConfrontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limite_valor_apostado' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'limite_valor_apostado.required' => 'Informe o limite de valor apostado.',
            'limite_valor_apostado.numeric' => 'O limite de valor apostado deve ser um número.',
            'limite_valor_apostado.gt' => 'O limite de valor apostado deve ser maior que zero.',
            'limite_valor_apostado.regex' => 'O limite de valor apostado deve ter até 2 casas decimais.',
        ];
    }
}
