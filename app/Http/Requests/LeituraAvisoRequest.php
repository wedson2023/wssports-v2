<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aparelho que pede o aviso ou marca como lido: identificador (UUID) guardado no navegador.
 */
class LeituraAvisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // rota pública
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aparelho' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aparelho.required' => 'Informe o aparelho.',
            'aparelho.uuid' => 'O aparelho informado é inválido.',
        ];
    }
}
