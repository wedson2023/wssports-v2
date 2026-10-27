<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de afiliado pela gestão do painel (contracts/api §2.3).
 */
class StoreAfiliadosRequest extends FormRequest
{
    use DadosAfiliado;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_afiliado();

        $this->merge(['ddi' => $this->input('ddi') ?: '55']);

        // o Gerente fica como responsável: o valor enviado é ignorado, não recusado (FR-009)
        if ($this->user()?->funcao() === Funcao::Gerente) {
            $this->request->remove('usuarios_id');
            $this->json()->remove('usuarios_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->regras_dados_afiliado('required'),
            'login' => $this->regras_login(),
            'password' => ['required', 'confirmed', $this->regra_senha()],
            // Admin e Supervisor escolhem o responsável, obrigatório (spec 019, FR-003d); o do Gerente
            // é ele mesmo
            'usuarios_id' => $this->user()?->funcao() === Funcao::Gerente
                ? ['nullable']
                : ['required', 'integer', $this->regra_gerente()],
            // regra de ganho obrigatória para quem cadastra, inclusive o Gerente (spec 022, FR-012)
            ...$this->regras_comissao_obrigatoria(),
            ...$this->regras_perfil_afiliado('required'),
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
            'usuarios_id.required' => 'O gerente responsável é obrigatório.',
        ];
    }
}
