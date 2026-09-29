<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Carteira;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validação dos filtros do extrato, usada pelo painel e pela área do cliente.
 */
trait FiltrosExtrato
{
    /**
     * @return array{data_inicial?: string, data_final?: string, carteira?: string, por_pagina?: int}
     */
    protected function validar_filtros_extrato(Request $request): array
    {
        // filtros enviados vazios chegam como null e são ignorados
        return $request->validate([
            'data_inicial' => ['nullable', 'date_format:Y-m-d'],
            'data_final' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicial'],
            'carteira' => ['nullable', Rule::enum(Carteira::class)],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'data_inicial.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'data_final.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'data_final.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'carteira.enum' => 'A carteira informada é inválida.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);
    }
}
