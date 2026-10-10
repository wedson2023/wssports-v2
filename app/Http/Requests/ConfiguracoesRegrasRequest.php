<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Texto das regras da banca: texto simples, um parágrafo por linha; vazio esconde o bloco.
 */
class ConfiguracoesRegrasRequest extends FormRequest
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
            'regras' => ['present', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'regras.present' => 'Informe o texto das regras (pode ser vazio).',
            'regras.string' => 'O texto das regras deve ser um texto.',
            'regras.max' => 'O texto das regras deve ter no máximo 10.000 caracteres.',
        ];
    }
}
