<?php

namespace App\Http\Requests;

use App\Enums\ModalidadePromocao;
use App\Enums\SituacaoBonus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros da aba Bônus recebidos (spec 020, FR-020 e FR-023): situação, busca, promoção, modalidade e
 * período de recebimento (dias de Brasília, até 1 ano).
 */
class BonusRecebidosFiltrosRequest extends FormRequest
{
    /**
     * Situação que mostra todos os bônus.
     */
    public const TODOS = 'Todos';

    private const DIAS_MAXIMOS_PERIODO = 366;

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
            'situacao' => ['nullable', Rule::in([...array_column(SituacaoBonus::cases(), 'value'), self::TODOS])],
            'busca' => ['nullable', 'string', 'max:150'],
            'clientes_promocoes_id' => ['nullable', 'integer'],
            'modalidade' => ['nullable', Rule::enum(ModalidadePromocao::class)],
            'data_inicial' => ['nullable', 'required_with:data_final', 'date_format:Y-m-d'],
            'data_final' => ['nullable', 'required_with:data_inicial', 'date_format:Y-m-d', 'after_or_equal:data_inicial'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'situacao.in' => 'A situação informada é inválida.',
            'busca.*' => 'A busca deve ter no máximo 150 caracteres.',
            'clientes_promocoes_id.integer' => 'Promoção não encontrada.',
            'modalidade.enum' => 'A modalidade deve ser Esportes ou Cassino.',
            'data_inicial.required_with' => 'Informe a data inicial do período.',
            'data_final.required_with' => 'Informe a data final do período.',
            '*.date_format' => 'A data informada é inválida.',
            'data_final.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $inicial = $this->input('data_inicial');
                $final = $this->input('data_final');

                if ($validator->errors()->isEmpty() && $inicial && $final
                    && Carbon::parse($inicial)->diffInDays(Carbon::parse($final)) >= self::DIAS_MAXIMOS_PERIODO) {
                    $validator->errors()->add('data_final', 'O período deve ter no máximo 1 ano.');
                }
            },
        ];
    }
}
