<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Troca da senha com o código recebido por e-mail (contracts/api §1.5).
 */
class RedefinirSenhaAfiliadoRequest extends RecuperarSenhaAfiliadoRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'codigo' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $regra_senha = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';

        return [
            ...parent::messages(),
            'codigo.required' => 'O código é obrigatório.',
            'codigo.digits' => 'O código deve ter 6 dígitos.',
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => $regra_senha,
            'password.letters' => $regra_senha,
            'password.numbers' => $regra_senha,
        ];
    }
}
