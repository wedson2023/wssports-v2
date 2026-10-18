<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Prestação de contas do afiliado (contracts/api §2.13): confirmada com a senha de quem presta.
 */
class PrestacaoContasAfiliadoRequest extends FormRequest
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
            'observacao' => ['nullable', 'string', 'max:500'],
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
            'observacao.string' => 'A observação deve ser um texto.',
            'observacao.max' => 'A observação deve ter no máximo 500 caracteres.',
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
