<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Troca do código do acesso assistido pelo token (spec 007, US7).
 */
class IniciarAcessoAssistidoRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'size:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'Acesso expirado. Gere um novo pelo painel.',
            'codigo.size' => 'Acesso expirado. Gere um novo pelo painel.',
        ];
    }
}
