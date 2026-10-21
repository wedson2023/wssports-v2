<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Desfazer a última prestação de contas (spec 017, FR-022a, contracts/api.md §3.5): motivo e senha.
 */
class DesfazerPrestacaoCaixaRequest extends FormRequest
{
    use ConfirmacaoCaixa;

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
            'motivo' => ['required', 'string', 'max:500'],
            'senha' => ['required', 'string', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo para desfazer a prestação.',
            'motivo.*' => 'O motivo deve ter no máximo 500 caracteres.',
            'senha.required' => 'Confirme com a sua senha.',
            'senha.string' => 'Confirme com a sua senha.',
        ];
    }
}
