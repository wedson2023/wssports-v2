<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VencedorasEspecial;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Correção do resultado de uma categoria especial encerrada (spec 024, R-09): as mesmas regras de
 * vencedoras do encerramento, confirmada com a senha de quem corrige, como a exclusão do afiliado.
 */
class CorrigirResultadoEspecialRequest extends FormRequest
{
    use VencedorasEspecial;

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
            ...$this->regras_vencedoras(),
            'password' => ['required', 'string', $this->regra_senha_do_usuario()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_vencedoras(),
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
