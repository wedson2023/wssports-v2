<?php

namespace App\Http\Requests;

use App\Services\SaldoClientes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido de saque do cliente (spec 007, contracts/api.md §7).
 */
class SaquesRequest extends FormRequest
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
            'valor' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valor.required' => 'Informe o valor do saque.',
            'valor.numeric' => 'O valor do saque deve ser um número.',
            'valor.regex' => 'O valor do saque deve ter até 2 casas decimais.',
            'valor.gt' => 'O valor do saque deve ser maior que zero.',
        ];
    }

    /**
     * Valor com duas casas ("100" → "100.00").
     */
    public function valor(): string
    {
        return SaldoClientes::para_decimal(SaldoClientes::para_centavos((string) $this->validated('valor')));
    }
}
