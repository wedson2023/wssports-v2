<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuariosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // a permissão de cadastrar é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->login)) {
            $this->merge(['login' => trim($this->login)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'funcao' => ['required', Rule::enum(Funcao::class), $this->regra_funcao_permitida()],
            'login' => ['required', 'string', 'max:255', $this->regra_login_unico()],
            'password' => ['required', 'string', 'min:6'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'usuarios_id' => ['prohibited'],
            'ativo' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
            'funcao.required' => 'A função é obrigatória.',
            'funcao.enum' => 'A função informada é inválida.',
            'login.required' => 'O login é obrigatório.',
            'login.string' => 'O login deve ser um texto.',
            'login.max' => 'O login deve ter no máximo 255 caracteres.',
            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto.',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres.',
            'telefone.string' => 'O telefone deve ser um texto.',
            'telefone.max' => 'O telefone deve ter no máximo 20 caracteres.',
            'endereco.string' => 'O endereço deve ser um texto.',
            'endereco.max' => 'O endereço deve ter no máximo 255 caracteres.',
            'usuarios_id.prohibited' => 'O superior não pode ser informado no cadastro.',
            'ativo.prohibited' => 'A situação não pode ser informada no cadastro.',
        ];
    }

    /**
     * Quem cadastra só pode criar a função imediatamente abaixo da sua.
     */
    private function regra_funcao_permitida(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $funcao_permitida = $this->user()->funcao()?->funcao_abaixo();

            if ($funcao_permitida === null) {
                $falhar('Sua função não permite cadastrar usuários.');
            } elseif ($valor !== $funcao_permitida->value) {
                $falhar("Você só pode cadastrar usuários com a função {$funcao_permitida->value}.");
            }
        };
    }

    /**
     * Login único sem diferenciar maiúsculas/minúsculas, incluindo usuários excluídos.
     */
    private function regra_login_unico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $login_em_uso = Usuarios::withTrashed()
                ->whereRaw('LOWER(login) = ?', [mb_strtolower($valor)])
                ->exists();

            if ($login_em_uso) {
                $falhar('O login informado já está em uso.');
            }
        };
    }
}
