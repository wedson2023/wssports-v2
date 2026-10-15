<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações gerais do encerramento de aposta (spec 010, FR-036, R-18). Só os campos enviados são
 * gravados.
 */
class ConfiguracoesEncerramentoRequest extends FormRequest
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
            'encerramento_habilitado' => ['sometimes', 'boolean'],
            'encerramento_parcial_habilitado' => ['sometimes', 'boolean'],
            'encerramento_automatico_habilitado' => ['sometimes', 'boolean'],
            'encerramento_jogos_nao_iniciados' => ['sometimes', 'boolean'],
            'margem_encerramento_pre_jogo' => ['sometimes', 'numeric', 'between:0,50'],
            'margem_encerramento_ao_vivo' => ['sometimes', 'numeric', 'between:0,50'],
            'valor_minimo_encerramento' => ['sometimes', 'numeric', 'between:0.01,10000'],
            'percentual_minimo_encerramento' => ['sometimes', 'numeric', 'between:0,100'],
            'segundos_validade_oferta' => ['sometimes', 'integer', 'between:3,60'],
            'limite_encerramentos_parciais' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'margem_encerramento_pre_jogo.*' => 'A margem do pré-jogo deve ser de 0 a 50%.',
            'margem_encerramento_ao_vivo.*' => 'A margem do ao vivo deve ser de 0 a 50%.',
            'valor_minimo_encerramento.*' => 'O valor mínimo do encerramento deve ser de R$ 0,01 a R$ 10.000,00.',
            'percentual_minimo_encerramento.*' => 'O percentual mínimo do encerramento deve ser de 0 a 100%.',
            'segundos_validade_oferta.*' => 'A validade da oferta deve ser de 3 a 60 segundos.',
            'limite_encerramentos_parciais.*' => 'O limite de encerramentos parciais deve ser de 1 a 50.',
        ];
    }
}
