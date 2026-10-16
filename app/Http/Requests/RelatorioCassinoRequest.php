<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Filtros do relatório do cassino no painel (spec 011, FR-032, contracts/api.md §3.6).
 */
class RelatorioCassinoRequest extends FormRequest
{
    private const DIAS_MAXIMOS = 366;

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
            'data_inicial' => ['required', 'date'],
            'data_final' => ['required', 'date', 'after_or_equal:data_inicial'],
            'agrupar' => ['nullable', 'in:jogo,provedor,canal'],
            'provedor' => ['nullable', 'integer', 'exists:cassinos_provedores,id'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $dias = CarbonImmutable::parse($this->input('data_inicial'))->diffInDays(CarbonImmutable::parse($this->input('data_final')));

            if ($dias > self::DIAS_MAXIMOS) {
                $validador->errors()->add('data_final', 'O período do relatório deve ter no máximo 366 dias.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data_inicial.required' => 'Informe a data inicial.',
            'data_final.required' => 'Informe a data final.',
            '*.date' => 'A data informada é inválida.',
            'data_final.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'agrupar.in' => 'O agrupamento deve ser jogo, provedor ou canal.',
            'provedor.*' => 'O provedor informado não existe.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
