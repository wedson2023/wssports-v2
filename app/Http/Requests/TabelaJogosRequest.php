<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros da tabela de jogos impressa pelo vendedor: dia (hoje ou amanhã) ou lista de campeonatos.
 */
class TabelaJogosRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão é conferida pelo middleware do controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dia' => ['nullable', 'in:hoje,amanha'],
            'esporte' => ['nullable', 'string', 'max:30'],
            'campeonatos' => ['nullable', 'array', 'max:100'],
            'campeonatos.*' => ['integer', 'min:1', 'distinct'],
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
            'dia.in' => 'O dia deve ser hoje ou amanha.',
            'esporte.string' => 'O esporte deve ser um texto.',
            'esporte.max' => 'O esporte deve ter no máximo 30 caracteres.',
            'campeonatos.array' => 'Os campeonatos devem ser uma lista.',
            'campeonatos.max' => 'Escolha no máximo 100 campeonatos.',
            'campeonatos.*.integer' => 'Cada campeonato deve ser um número inteiro.',
            'campeonatos.*.min' => 'Cada campeonato deve ser um número inteiro.',
            'campeonatos.*.distinct' => 'Há campeonatos repetidos.',
            'pagina.integer' => 'A página deve ser um número inteiro.',
            'pagina.min' => 'A página deve ser pelo menos 1.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Filtros com os valores padrão: hoje, futebol, 100 por página.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        $dados = $this->validated();

        return [
            'dia' => $dados['dia'] ?? 'hoje',
            'esporte' => mb_strtoupper($dados['esporte'] ?? 'FUTEBOL'),
            'campeonatos' => $dados['campeonatos'] ?? [],
            'pagina' => (int) ($dados['pagina'] ?? 1),
            'por_pagina' => (int) ($dados['por_pagina'] ?? 100),
        ];
    }
}
