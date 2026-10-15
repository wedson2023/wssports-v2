<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações gerais da apuração (spec 009, R-16): remoção automática dos jogos cancelados e
 * janela de pagamento dos prêmios. Só os campos enviados são gravados.
 */
class ConfiguracoesApuracaoRequest extends FormRequest
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
            'remover_confronto_cancelado' => ['sometimes', 'boolean'],
            'minutos_pagamento_premio' => ['sometimes', 'integer', 'between:0,1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remover_confronto_cancelado.boolean' => 'O campo remover_confronto_cancelado deve ser verdadeiro ou falso.',
            'minutos_pagamento_premio.integer' => 'A espera para pagar o prêmio deve ser de 0 a 1440 minutos.',
            'minutos_pagamento_premio.between' => 'A espera para pagar o prêmio deve ser de 0 a 1440 minutos.',
        ];
    }
}
