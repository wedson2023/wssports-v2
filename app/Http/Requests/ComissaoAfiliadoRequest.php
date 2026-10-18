<?php

namespace App\Http\Requests;

use App\Enums\TipoGanhoAfiliado;
use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Troca da regra de comissão do afiliado (contracts/api §2.9).
 */
class ComissaoAfiliadoRequest extends FormRequest
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
            'tipo_ganho' => ['required', Rule::enum(TipoGanhoAfiliado::class)],
            'percentual' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
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
