<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de uma seção da Dashboard (spec 014, contracts/api.md §2): `atualizar` ignora o cache.
 */
class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão de cada seção é checada pelo middleware do controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'atualizar' => ['sometimes', 'boolean'],
            'depois_de' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'atualizar.boolean' => 'O campo atualizar deve ser verdadeiro ou falso.',
            'depois_de.*' => 'O último aviso visto é inválido.',
        ];
    }

    public function atualizar(): bool
    {
        return $this->boolean('atualizar');
    }
}
