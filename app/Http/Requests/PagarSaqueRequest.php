<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprovação de um saque no painel: baixa manual ou pagamento pelo Mercado Pago (spec 007).
 */
class PagarSaqueRequest extends FormRequest
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
            'forma' => ['required', 'in:manual,mercado_pago'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'forma.required' => 'Informe a forma de pagamento do saque.',
            'forma.in' => 'A forma deve ser manual ou mercado_pago.',
        ];
    }
}
