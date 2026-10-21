<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Prestação de contas de um vendedor (spec 017, FR-017 a FR-019, contracts/api.md §3.2): até quando,
 * valor recebido (vazio = o apurado), observação e a senha de quem presta.
 */
class PrestacaoContasCaixaRequest extends FormRequest
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
            'valor_recebido' => ['nullable', 'numeric', 'decimal:0,2', 'between:-9999999999999.99,9999999999999.99'],
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
            'valor_recebido.*' => 'Informe um valor recebido válido, com até duas casas.',
            'observacao.*' => 'A observação deve ter no máximo 500 caracteres.',
            'senha.required' => 'Confirme com a sua senha.',
            'senha.string' => 'Confirme com a sua senha.',
        ];
    }

    public function ate(): Carbon
    {
        return $this->data_hora('ate');
    }

    public function valor_recebido(): ?string
    {
        $valor = $this->validated('valor_recebido');

        return $valor === null ? null : bcadd((string) $valor, '0', 2);
    }
}
