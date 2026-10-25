<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Estorno do bônus de um cliente (spec 020, FR-027): motivo e a senha de quem estorna.
 */
class EstornarBonusRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:255'],
            'senha' => ['required', 'string', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'O motivo do estorno é obrigatório.',
            'motivo.max' => 'O motivo deve ter no máximo 255 caracteres.',
            'senha.required' => 'Informe a sua senha.',
        ];
    }
}
