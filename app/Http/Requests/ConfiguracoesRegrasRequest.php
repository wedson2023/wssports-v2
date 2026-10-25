<?php

namespace App\Http\Requests;

use App\Services\LimpezaHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Texto das regras da banca (spec 006, FR-021b), em HTML do editor desde a spec 021 (FR-018): até
 * 10.000 caracteres de texto visível e 50.000 contando a formatação; o HTML é limpo antes de gravar.
 */
class ConfiguracoesRegrasRequest extends FormRequest
{
    private const MAXIMO_TEXTO = 10000;

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
            'regras' => ['present', 'nullable', 'string', 'max:50000'],
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
            'regras.max' => 'As regras ficaram grandes demais com a formatação; diminua o texto.',
        ];
    }

    /**
     * O limite de 10.000 caracteres vale para o texto que o administrador vê, sem as marcações.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $html = $this->input('regras');

                if (is_string($html) && mb_strlen(app(LimpezaHtml::class)->texto($html)) > self::MAXIMO_TEXTO) {
                    $validator->errors()->add('regras', 'As regras devem ter no máximo 10.000 caracteres de texto.');
                }
            },
        ];
    }

    /**
     * HTML das regras limpo para gravar; vazio grava "" (esconde o bloco sem voltar ao padrão).
     */
    public function regras_limpas(): string
    {
        return app(LimpezaHtml::class)->limpar($this->validated('regras'));
    }
}
