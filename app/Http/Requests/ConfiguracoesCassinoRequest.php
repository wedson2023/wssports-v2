<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações gerais do cassino (spec 011, FR-031, contracts/api.md §3.8). Só os campos enviados são
 * gravados.
 */
class ConfiguracoesCassinoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cassino_habilitado' => ['sometimes', 'boolean'],
            'cassino_demo_habilitado' => ['sometimes', 'boolean'],
            'data_prestacao_contas_cassino' => ['sometimes', 'nullable', 'date'],
            'dias_registro_chamadas_cassino' => ['sometimes', 'integer', 'between:1,90'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'data_prestacao_contas_cassino.date' => 'A data da prestação de contas é inválida.',
            'dias_registro_chamadas_cassino.*' => 'Os dias de registro das chamadas devem ser de 1 a 90.',
        ];
    }
}
