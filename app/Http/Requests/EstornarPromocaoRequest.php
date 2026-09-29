<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstornarPromocaoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'O motivo do estorno é obrigatório.',
            'motivo.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
