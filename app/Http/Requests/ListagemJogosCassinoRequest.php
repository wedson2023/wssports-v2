<?php

namespace App\Http\Requests;

use App\Enums\AparelhoCassino;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros da página inicial e da grade de jogos do cassino (spec 011, contracts/api.md §1).
 */
class ListagemJogosCassinoRequest extends FormRequest
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
            'aparelho' => ['required', 'in:celular,computador'],
            'provedor' => ['nullable', 'integer', 'exists:cassinos_provedores,id'],
            'tipo' => ['nullable', 'string', 'max:30'],
            'busca' => ['nullable', 'string', 'between:2,60'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aparelho.required' => 'Informe o aparelho (celular ou computador).',
            'aparelho.in' => 'O aparelho deve ser celular ou computador.',
            'provedor.*' => 'O provedor informado não existe.',
            'tipo.*' => 'O tipo informado é inválido.',
            'busca.between' => 'A busca deve ter de 2 a 60 caracteres.',
            'pagina.*' => 'A página deve ser um número inteiro a partir de 1.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    public function aparelho(): AparelhoCassino
    {
        return AparelhoCassino::de_quem_ve((string) $this->validated('aparelho'));
    }
}
