<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Antecipação dos pendentes de um cliente (spec 019, FR-019), confirmada com a senha de quem
 * antecipa, como as demais operações de dinheiro do painel.
 */
class AnteciparPendentesRequest extends FormRequest
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
            'senha' => ['required', 'string', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'senha.required' => 'Informe a sua senha para confirmar.',
        ];
    }
}
