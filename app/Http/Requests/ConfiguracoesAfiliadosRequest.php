<?php

namespace App\Http\Requests;

use App\Enums\TipoGanhoAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regra de comissão padrão do cadastro público de afiliados (spec 012, FR-015).
 */
class ConfiguracoesAfiliadosRequest extends FormRequest
{
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
            'afiliados_tipo_ganho_padrao' => ['sometimes', Rule::enum(TipoGanhoAfiliado::class)],
            'afiliados_percentual_padrao' => ['sometimes', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'afiliados_tipo_ganho_padrao.enum' => 'O tipo de ganho deve ser Saldo líquido, Qualquer depósito ou Primeiro depósito.',
            'afiliados_percentual_padrao.numeric' => 'O percentual deve ser um número.',
            'afiliados_percentual_padrao.between' => 'O percentual deve estar entre 0 e 100.',
            'afiliados_percentual_padrao.decimal' => 'O percentual deve ter no máximo 2 casas decimais.',
        ];
    }
}
