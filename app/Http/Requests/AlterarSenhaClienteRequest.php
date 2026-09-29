<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;

class AlterarSenhaClienteRequest extends FormRequest
{
    use DadosCliente;

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
            'senha_atual' => ['required', 'current_password:clientes'],
            'password' => ['required', 'confirmed', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_senha(),
            'senha_atual.required' => 'A senha atual é obrigatória.',
            'senha_atual.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'A nova senha é obrigatória.',
        ];
    }
}
