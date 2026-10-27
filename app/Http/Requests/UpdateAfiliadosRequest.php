<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edição do afiliado pela gestão (contracts/api §2.5). O login não muda; gerente responsável e
 * código só para Admin e Supervisor; a regra de comissão tem rota própria.
 */
class UpdateAfiliadosRequest extends FormRequest
{
    use DadosAfiliado;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_afiliado();

        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => mb_strtoupper(trim($this->input('codigo')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $afiliado_id = $this->route('afiliado')->id;
        $gestor_geral = in_array($this->user()->funcao(), [Funcao::Admin, Funcao::Supervisor], true);
        $regras = $this->regras_dados_afiliado('sometimes', $afiliado_id);

        return [
            ...$regras,
            ...$this->regras_perfil_afiliado('sometimes', $this->route('afiliado')),
            'pix_tipo_chave' => [...$regras['pix_tipo_chave'], 'required_with:pix_chave'],
            'pix_chave' => [...$regras['pix_chave'], 'required_with:pix_tipo_chave'],
            'login' => ['prohibited'],
            'password' => ['sometimes', 'required', 'confirmed', $this->regra_senha()],
            // o gerente responsável não pode ser retirado (spec 019, FR-003d)
            'usuarios_id' => $gestor_geral ? ['sometimes', 'required', 'integer', $this->regra_gerente()] : ['prohibited'],
            'codigo' => $gestor_geral
                ? ['sometimes', 'required', 'string', 'regex:/^[A-Z0-9._-]{4,50}$/', Rule::unique('afiliados', 'codigo')->ignore($afiliado_id)]
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_dados_afiliado(),
            ...$this->mensagens_comissao(),
            'pix_tipo_chave.required_with' => 'Informe o tipo de chave Pix junto com a chave.',
            'pix_chave.required_with' => 'Informe a chave Pix junto com o tipo.',
            'login.prohibited' => 'O login não pode ser alterado.',
            'usuarios_id.prohibited' => 'Só Admin e Supervisor alteram o gerente responsável.',
            'usuarios_id.required' => 'O gerente responsável é obrigatório.',
            'codigo.prohibited' => 'Só Admin e Supervisor alteram o código.',
            'codigo.required' => 'O código é obrigatório.',
            'codigo.regex' => 'O código deve ter de 4 a 50 caracteres, só com letras, números, ponto, hífen ou sublinhado.',
            'codigo.unique' => 'O código já está em uso.',
        ];
    }
}
