<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recusa de um saque no painel, com o motivo que o cliente vê (spec 007, FR-038).
 */
class RecusarSaqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da recusa.',
            'motivo.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
