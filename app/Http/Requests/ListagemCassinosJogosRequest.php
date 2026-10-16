<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros do catálogo do cassino no painel (spec 011, contracts/api.md §3.1).
 */
class ListagemCassinosJogosRequest extends FormRequest
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
            'busca' => ['nullable', 'string', 'max:60'],
            'provedor' => ['nullable', 'integer', 'exists:cassinos_provedores,id'],
            'tipo' => ['nullable', 'string', 'max:30'],
            'ativo' => ['nullable', 'boolean'],
            'popular' => ['nullable', 'boolean'],
            'removido' => ['nullable', 'in:sim,nao'],
            'sem_canal_abrivel' => ['nullable', 'boolean'],
            'repetidos' => ['nullable', 'boolean'],
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
            'provedor.*' => 'O provedor informado não existe.',
            'removido.in' => 'O filtro removido deve ser sim ou nao.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
