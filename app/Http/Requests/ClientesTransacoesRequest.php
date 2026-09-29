<?php

namespace App\Http\Requests;

use App\Enums\Carteira;
use App\Enums\TipoTransacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crédito ou débito manual no saldo de um cliente, pelo painel.
 */
class ClientesTransacoesRequest extends FormRequest
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
            'carteira' => ['required', Rule::enum(Carteira::class)],
            'tipo' => ['required', Rule::enum(TipoTransacao::class)],
            'valor' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'gt:0'],
            'observacao' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carteira.required' => 'A carteira é obrigatória.',
            'carteira.enum' => 'A carteira informada é inválida.',
            'tipo.required' => 'O tipo é obrigatório.',
            'tipo.enum' => 'O tipo informado é inválido.',
            'valor.required' => 'O valor é obrigatório.',
            'valor.regex' => 'O valor deve ser maior que zero, com até 2 casas decimais.',
            'valor.gt' => 'O valor deve ser maior que zero, com até 2 casas decimais.',
            'observacao.required' => 'O motivo é obrigatório.',
            'observacao.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
