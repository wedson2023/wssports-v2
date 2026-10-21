<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Models\Afiliados;
use App\Models\Usuarios;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de usuário do painel (spec 001, alterado pela spec 015): qualquer função abaixo de quem
 * cadastra; fora da função imediatamente abaixo, o superior é escolhido na rede de quem cadastra; o
 * vendedor pode vir com as comissões de 1, 2 e 3 jogos.
 */
class StoreUsuariosRequest extends FormRequest
{
    private const REGRA_COMISSAO = ['sometimes', 'nullable', 'numeric', 'between:0,100', 'regex:/^\d+(\.\d{1,2})?$/'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // a permissão de cadastrar a função é checada no controller
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
        $funcao = Funcao::tryFrom((string) $this->input('funcao'));
        $comissao = $funcao === Funcao::Vendedor ? self::REGRA_COMISSAO : ['prohibited'];

        return [
            'nome' => ['required', 'string', 'max:255'],
            'funcao' => ['required', Rule::enum(Funcao::class), $this->regra_funcao_permitida()],
            'login' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', $this->regra_login_unico()],
            'password' => ['required', 'string', 'min:6'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'usuarios_id' => $this->exige_superior($funcao)
                ? ['required', 'integer', $this->regra_superior_valido($funcao)]
                : ['prohibited'],
            'ativo' => ['prohibited'],
            'comissao_1' => $comissao,
            'comissao_2' => $comissao,
            'comissao_3' => $comissao,
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
            'login.regex' => 'O login deve ter só letras sem acento, números, ponto, hífen ou sublinhado, sem espaços.',
            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto.',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres.',
            'telefone.string' => 'O telefone deve ser um texto.',
            'telefone.max' => 'O telefone deve ter no máximo 20 caracteres.',
            'endereco.string' => 'O endereço deve ser um texto.',
            'endereco.max' => 'O endereço deve ter no máximo 255 caracteres.',
            'usuarios_id.required' => 'Informe o superior.',
            'usuarios_id.integer' => 'O superior informado é inválido.',
            'usuarios_id.prohibited' => 'O superior não pode ser informado para esta função: o usuário fica na sua equipe.',
            'ativo.prohibited' => 'A situação não pode ser informada no cadastro.',
            'comissao_*.numeric' => 'A comissão deve ser um número.',
            'comissao_*.between' => 'A comissão deve ser de 0 a 100%.',
            'comissao_*.regex' => 'A comissão deve ter até 2 casas decimais.',
            'comissao_*.prohibited' => 'As comissões só podem ser informadas no cadastro de vendedor.',
        ];
    }

    /**
     * O superior é escolhido quando a função cadastrada não é a imediatamente abaixo de quem cadastra.
     */
    public function exige_superior(?Funcao $funcao): bool
    {
        return $funcao !== null && $funcao !== $this->user()->funcao()?->funcao_abaixo();
    }

    /**
     * Quem cadastra só pode criar funções abaixo da sua.
     */
    private function regra_funcao_permitida(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $propria = $this->user()->funcao();
            $pedida = Funcao::tryFrom((string) $valor);

            if ($propria === null || $propria->funcao_abaixo() === null) {
                $falhar('Sua função não permite cadastrar usuários.');
            } elseif ($pedida === null || $pedida->nivel() <= $propria->nivel()) {
                $falhar("Você não pode cadastrar usuários com a função {$valor}.");
            }
        };
    }

    /**
     * Superior da função logo acima da cadastrada e na rede de quem cadastra.
     */
    private function regra_superior_valido(?Funcao $funcao): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) use ($funcao) {
            $superior = Usuarios::find($valor);

            $valido = $superior !== null
                && $superior->funcao() === $funcao?->funcao_acima()
                && $this->user()->gerencia($superior);

            if (! $valido) {
                $falhar('O superior informado é inválido para a função deste usuário.');
            }
        };
    }

    /**
     * Login único sem diferenciar maiúsculas/minúsculas, incluindo usuários excluídos e também os
     * logins dos afiliados, que entram pela mesma tela (spec 012, FR-002).
     */
    private function regra_login_unico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $login = mb_strtolower($valor);
            $login_em_uso = Usuarios::withTrashed()->whereRaw('LOWER(login) = ?', [$login])->exists()
                || Afiliados::withTrashed()->whereRaw('LOWER(login) = ?', [$login])->exists();

            if ($login_em_uso) {
                $falhar('O login informado já está em uso.');
            }
        };
    }
}
