<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido de novo link de confirmação de e-mail (spec 025, contracts/api.md).
 */
class ReenviarConfirmacaoEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ddi' => $this->input('ddi') ?: '55',
            'telefone' => preg_replace('/\D/', '', (string) $this->input('telefone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ddi' => ['digits_between:1,3'],
            'telefone' => ['required', 'digits_between:8,15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telefone.required' => 'Informe o telefone do cadastro.',
            'telefone.digits_between' => 'Informe um telefone válido.',
        ];
    }
}
