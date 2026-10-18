<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprovação do cadastro público (contracts/api §2.7): gerente responsável e regra opcionais.
 */
class AprovarAfiliadoRequest extends FormRequest
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
            'usuarios_id' => ['sometimes', 'nullable', 'integer', $this->regra_gerente()],
            ...$this->regras_comissao_opcional(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensagens_comissao();
    }
}
