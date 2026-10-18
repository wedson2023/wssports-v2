<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Troca de senha pelo próprio afiliado (contracts/api §1.11): pede a senha atual.
 */
class AlterarSenhaAfiliadoRequest extends FormRequest
{
    use DadosAfiliado;

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
            'senha_atual' => ['required', 'string', $this->regra_senha_atual()],
            'password' => ['required', 'confirmed', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_dados_afiliado(),
            'senha_atual.required' => 'Informe a senha atual.',
            'senha_atual.string' => 'Informe a senha atual.',
        ];
    }

    private function regra_senha_atual(): Closure
    {
        return function (string $atributo, mixed $senha, Closure $falhar) {
            if (! Hash::check((string) $senha, $this->user('afiliados')->password)) {
                $falhar('A senha atual está incorreta.');
            }
        };
    }
}
