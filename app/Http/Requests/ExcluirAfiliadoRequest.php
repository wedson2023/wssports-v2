<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Exclusão do afiliado para a lixeira (contracts/api §2.7 da spec 022): confirmada com a senha de
 * quem exclui, como a prestação de contas.
 */
class ExcluirAfiliadoRequest extends FormRequest
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
            'password' => ['required', 'string', $this->regra_senha_do_usuario()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Confirme com a sua senha.',
            'password.string' => 'Confirme com a sua senha.',
        ];
    }

    private function regra_senha_do_usuario(): Closure
    {
        return function (string $atributo, mixed $senha, Closure $falhar) {
            if (! Hash::check((string) $senha, $this->user()->password)) {
                $falhar('Senha incorreta.');
            }
        };
    }
}
