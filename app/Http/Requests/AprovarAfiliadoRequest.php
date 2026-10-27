<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprovação do cadastro público (contracts/api §2.5 da spec 022): gerente responsável (spec 019,
 * FR-003d) e regra de ganho, os três obrigatórios (spec 022, FR-022).
 */
class AprovarAfiliadoRequest extends FormRequest
{
    use DadosAfiliado;

    /**
     * Só o Admin aprova (spec 022, FR-023); para os demais o cadastro sem gerente está fora da rede e
     * é "não encontrado", antes de qualquer validação.
     */
    public function authorize(): bool
    {
        abort_unless($this->user()->funcao() === Funcao::Admin, 404, 'Afiliado não encontrado.');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuarios_id' => ['required', 'integer', $this->regra_gerente()],
            ...$this->regras_comissao_obrigatoria(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...$this->mensagens_comissao(), 'usuarios_id.required' => 'Escolha o gerente responsável para aprovar.'];
    }
}
