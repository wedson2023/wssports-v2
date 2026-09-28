<?php

namespace App\Http\Requests;

use App\Models\Usuarios;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuariosRequest extends FormRequest
{
    /**
     * Campos de dados cadastrais; alterá-los exige a permissão usuarios.editar.
     */
    public const CAMPOS_DADOS = ['nome', 'password', 'telefone', 'endereco', 'usuarios_id'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // as permissões são checadas por campo no controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'endereco' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ativo' => ['sometimes', 'boolean'],
            'usuarios_id' => ['sometimes', 'integer', $this->regra_superior_valido()],
            'funcao' => ['prohibited'],
            'login' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
            'password.string' => 'A senha deve ser um texto.',
            'password.min' => 'A senha deve ter no mínimo 6 caracteres.',
            'telefone.string' => 'O telefone deve ser um texto.',
            'telefone.max' => 'O telefone deve ter no máximo 20 caracteres.',
            'endereco.string' => 'O endereço deve ser um texto.',
            'endereco.max' => 'O endereço deve ter no máximo 255 caracteres.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'usuarios_id.integer' => 'O superior informado é inválido para a função deste usuário.',
            'funcao.prohibited' => 'A função não pode ser alterada.',
            'login.prohibited' => 'O login não pode ser alterado.',
        ];
    }

    /**
     * Indica se a requisição altera algum dado cadastral (além da situação).
     */
    public function altera_dados(): bool
    {
        return $this->hasAny(self::CAMPOS_DADOS);
    }

    /**
     * O novo superior deve existir, não estar excluído, ter a função imediatamente acima da do
     * usuário editado e ser quem solicita ou alguém da sua sub-hierarquia. Como o superior
     * sempre fica um nível acima, a hierarquia nunca forma ciclos.
     */
    private function regra_superior_valido(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $alvo = $this->route('usuario');
            $solicitante = $this->user();
            $superior = Usuarios::find($valor);

            $superior_valido = $superior !== null
                && $superior->funcao() === $alvo->funcao()?->funcao_acima()
                && ($superior->is($solicitante) || $solicitante->gerencia($superior));

            if (! $superior_valido) {
                $falhar('O superior informado é inválido para a função deste usuário.');
            }
        };
    }
}
