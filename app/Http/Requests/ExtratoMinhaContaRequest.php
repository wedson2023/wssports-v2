<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros do extrato da tela de créditos (spec 007, FR-010): operação, situação do palpite e página.
 */
class ExtratoMinhaContaRequest extends FormRequest
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
            'operacao' => ['nullable', 'in:palpites,depositos,saques,bonus,cashback,estornos,cassino'],
            'situacao' => ['nullable', 'in:Aguardando,Vencedor,Perdedor', 'prohibited_unless:operacao,palpites'],
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
            'operacao.in' => 'A operação informada é inválida.',
            'situacao.in' => 'A situação informada é inválida.',
            'situacao.prohibited_unless' => 'A situação só pode ser usada com a operação Palpites.',
            'pagina.integer' => 'A página deve ser um número inteiro.',
            'pagina.min' => 'A página deve ser pelo menos 1.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
