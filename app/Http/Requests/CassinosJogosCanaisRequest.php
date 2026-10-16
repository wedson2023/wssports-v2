<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alteração de um canal de jogo do cassino no painel (spec 011, FR-030, R-06).
 */
class CassinosJogosCanaisRequest extends FormRequest
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
            'pre_pago' => ['sometimes', 'boolean'],
            'bonus_primeiro' => ['sometimes', 'boolean'],
            'prioridade' => ['sometimes', 'integer', 'between:0,999'],
            'disponivel' => ['sometimes', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'prioridade.*' => 'A prioridade deve ser um número de 0 a 999.',
            'disponivel.accepted' => 'Envie disponivel = true para liberar o canal.',
        ];
    }
}
