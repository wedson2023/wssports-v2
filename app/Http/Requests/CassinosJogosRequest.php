<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alteração de um jogo do cassino no painel (spec 011, FR-030): ativo, popular e ordem.
 */
class CassinosJogosRequest extends FormRequest
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
            'ativo' => ['sometimes', 'boolean'],
            'popular' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'between:0,65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'ordem.*' => 'A ordem deve ser um número de 0 a 65535.',
        ];
    }
}
