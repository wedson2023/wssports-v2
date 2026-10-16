<?php

namespace App\Http\Requests;

use App\Enums\ResultadoChamadaCassino;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros do registro de chamadas do provedor do cassino no painel (spec 011, FR-026, FR-030).
 */
class ListagemCassinosChamadasRequest extends FormRequest
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
            'resultado' => ['nullable', Rule::enum(ResultadoChamadaCassino::class)],
            'comando' => ['nullable', 'string', 'max:30'],
            'login' => ['nullable', 'string', 'max:60'],
            'trade_id' => ['nullable', 'string', 'max:100'],
            'data_inicial' => ['nullable', 'date'],
            'data_final' => ['nullable', 'date', 'after_or_equal:data_inicial'],
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
            'resultado.*' => 'O resultado deve ser Aceita, Repetida, Recusada ou Erro.',
            'data_inicial.date' => 'A data inicial é inválida.',
            'data_final.date' => 'A data final é inválida.',
            'data_final.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
