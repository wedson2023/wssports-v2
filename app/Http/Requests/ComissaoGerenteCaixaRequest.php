<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Percentual da comissão do gerente no caixa (spec 017, FR-027, contracts/api.md §1.5).
 */
class ComissaoGerenteCaixaRequest extends FormRequest
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
            'percentual' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'percentual.required' => 'Informe o percentual da comissão.',
            'percentual.*' => 'O percentual deve estar entre 0 e 100, com até duas casas.',
        ];
    }
}
