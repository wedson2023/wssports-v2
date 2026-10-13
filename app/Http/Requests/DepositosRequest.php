<?php

namespace App\Http\Requests;

use App\Enums\MeioDeposito;
use App\Services\SaldoClientes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido de depósito do cliente (spec 007, contracts/api.md §5).
 */
class DepositosRequest extends FormRequest
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
        $cartao = MeioDeposito::CartãoCrédito->value;

        return [
            'valor' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'gt:0'],
            'meio' => ['required', Rule::enum(MeioDeposito::class)],
            'aceita_bonus' => ['required', 'boolean'],
            'cartao' => ["required_if:meio,{$cartao}", 'array'],
            'cartao.token' => ["required_if:meio,{$cartao}", 'string', 'max:255'],
            'cartao.parcelas' => ["required_if:meio,{$cartao}", 'integer', 'min:1'],
            'cartao.payment_method_id' => ["required_if:meio,{$cartao}", 'string', 'max:50'],
            'cartao.issuer_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valor.required' => 'Informe o valor do depósito.',
            'valor.numeric' => 'O valor do depósito deve ser um número.',
            'valor.regex' => 'O valor do depósito deve ter até 2 casas decimais.',
            'valor.gt' => 'O valor do depósito deve ser maior que zero.',
            'meio.required' => 'Escolha o meio de pagamento.',
            'meio.enum' => 'Meio de pagamento indisponível.',
            'aceita_bonus.required' => 'Informe se aceita receber bônus.',
            'aceita_bonus.boolean' => 'O campo aceita bônus deve ser verdadeiro ou falso.',
            'cartao.required_if' => 'Informe os dados do cartão.',
            'cartao.*.required_if' => 'Informe os dados do cartão.',
            'cartao.parcelas.integer' => 'O número de parcelas deve ser inteiro.',
            'cartao.parcelas.min' => 'Escolha pelo menos 1 parcela.',
        ];
    }

    /**
     * Valor sempre com duas casas ("50" → "50.00"), para os cálculos com bcmath.
     */
    public function valor(): string
    {
        return SaldoClientes::para_decimal(SaldoClientes::para_centavos((string) $this->validated('valor')));
    }
}
