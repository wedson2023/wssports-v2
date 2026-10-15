<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valor-alvo do encerramento automático (spec 010, FR-030). A faixa (maior que a oferta atual e até
 * o prêmio potencial) é conferida pelo serviço.
 */
class EncerramentoAutomaticoRequest extends FormRequest
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
            'valor_alvo' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valor_alvo.required' => 'Informe o valor-alvo.',
            'valor_alvo.numeric' => 'O valor-alvo deve ser um número com até 2 casas.',
            'valor_alvo.gt' => 'O valor-alvo deve ser maior que zero.',
            'valor_alvo.regex' => 'O valor-alvo deve ser um número com até 2 casas.',
        ];
    }
}
