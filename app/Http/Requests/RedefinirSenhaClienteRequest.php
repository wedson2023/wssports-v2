<?php

namespace App\Http\Requests;

class RedefinirSenhaClienteRequest extends RecuperarSenhaClienteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'codigo' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...$this->mensagens_senha(),
            'codigo.required' => 'O código é obrigatório.',
            'codigo.digits' => 'O código deve ter 6 dígitos.',
        ];
    }
}
