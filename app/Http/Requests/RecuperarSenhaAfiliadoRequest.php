<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido do código de recuperação: login ou e-mail do afiliado (contracts/api §1.4).
 */
class RecuperarSenhaAfiliadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->identificacao)) {
            $this->merge(['identificacao' => mb_strtolower(trim($this->identificacao))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identificacao' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identificacao.required' => 'Informe o login ou o e-mail.',
            'identificacao.string' => 'Informe o login ou o e-mail.',
            'identificacao.max' => 'O login ou e-mail deve ter no máximo 150 caracteres.',
        ];
    }
}
