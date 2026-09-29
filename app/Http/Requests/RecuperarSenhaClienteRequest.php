<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;

class RecuperarSenhaClienteRequest extends FormRequest
{
    use DadosCliente;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_ddi_telefone();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ddi' => ['digits_between:1,3'],
            'telefone' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            'telefone.required' => 'O telefone é obrigatório.',
        ];
    }
}
