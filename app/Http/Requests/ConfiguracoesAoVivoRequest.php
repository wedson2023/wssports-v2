<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações do ao vivo editadas na aba Operação (spec 021, FR-029). A trava geral do ao vivo não
 * é aceita aqui: só a conferência liga e desliga (FR-030).
 */
class ConfiguracoesAoVivoRequest extends FormRequest
{
    private const VALOR = 'regex:/^\d+(\.\d{1,2})?$/';

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
            'ao_vivo_habilitado' => ['sometimes', 'boolean'],
            'segundos_trava_ao_vivo' => ['sometimes', 'integer', 'between:0,120'],
            'minutos_permanencia_ao_vivo' => ['sometimes', 'integer', 'between:1,60'],
            'minuto_limite_ao_vivo' => ['sometimes', 'integer', 'between:1,130'],
            'cotacao_maxima_ao_vivo' => ['sometimes', 'numeric', self::VALOR, 'between:1.01,1000'],
        ];
    }

    /**
     * Mensagens específicas primeiro: o Laravel usa a primeira que casar.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'segundos_trava_ao_vivo.between' => 'A trava do ao vivo deve ser de 0 a 120 segundos.',
            'minutos_permanencia_ao_vivo.between' => 'A permanência no ao vivo deve ser de 1 a 60 minutos.',
            'minuto_limite_ao_vivo.between' => 'O minuto limite do ao vivo deve ser de 1 a 130.',
            'cotacao_maxima_ao_vivo.between' => 'A cotação máxima do ao vivo deve ser de 1,01 a 1.000.',
            'cotacao_maxima_ao_vivo.regex' => 'A cotação máxima do ao vivo deve ter até 2 casas decimais.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.regex' => 'O campo :attribute deve ter até 2 casas decimais.',
        ];
    }
}
