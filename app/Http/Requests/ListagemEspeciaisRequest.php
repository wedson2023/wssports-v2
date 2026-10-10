<?php

namespace App\Http\Requests;

/**
 * Filtros da listagem pública de especiais, com o mesmo limite de requisições por IP da listagem
 * de jogos.
 */
class ListagemEspeciaisRequest extends ListagemPublicaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:150'],
            'especial' => ['nullable', 'integer', 'min:1'],
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
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'especial.integer' => 'A categoria deve ser um número inteiro.',
            'especial.min' => 'A categoria deve ser um número inteiro.',
            'pagina.integer' => 'A página deve ser um número inteiro.',
            'pagina.min' => 'A página deve ser pelo menos 1.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
