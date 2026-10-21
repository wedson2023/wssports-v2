<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Prestação de contas em lote dos vendedores de um gerente (spec 017, FR-021, contracts/api.md
 * §3.4): até quando, observação e a senha. O valor recebido é o apurado de cada vendedor.
 */
class PrestacaoContasLoteCaixaRequest extends FormRequest
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
            'ate' => ['bail', 'required', 'date', $this->regra_nao_futura()],
            'observacao' => ['nullable', 'string', 'max:500'],
            'senha' => ['required', 'string', $this->regra_senha()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ate.required' => 'Informe até quando prestar contas.',
            'ate.date' => 'A data informada é inválida.',
            'observacao.*' => 'A observação deve ter no máximo 500 caracteres.',
            'senha.required' => 'Confirme com a sua senha.',
            'senha.string' => 'Confirme com a sua senha.',
        ];
    }

    public function ate(): Carbon
    {
        return $this->data_hora('ate');
    }
}
