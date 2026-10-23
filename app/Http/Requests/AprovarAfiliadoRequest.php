<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprovação do cadastro público (contracts/api §2.7): regra opcional e gerente responsável, que
 * passa a ser obrigatório quando o afiliado ainda não tem um (spec 019, FR-003d).
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
        $sem_gerente = $this->route('afiliado')?->usuarios_id === null;

        return [
            'usuarios_id' => [$sem_gerente ? 'required' : 'sometimes', 'required', 'integer', $this->regra_gerente()],
            ...$this->regras_comissao_opcional(),
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
