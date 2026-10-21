<?php

namespace App\Http\Requests;

use App\Enums\TipoLancamentoCaixa;
use App\Http\Requests\Concerns\ConfirmacaoCaixa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Lançamento no caixa do vendedor (spec 017, FR-014, contracts/api.md §2.2): tipo, valor maior que
 * zero com até duas casas, data e hora (não futura) e observação.
 */
class StoreCaixaLancamentosRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoLancamentoCaixa::class)],
            'valor' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'ocorrido_em' => ['bail', 'required', 'date', $this->regra_nao_futura()],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Escolha o tipo do lançamento.',
            'tipo.*' => 'O tipo deve ser Suprimento ou Retirada.',
            'valor.required' => 'Informe o valor.',
            'valor.gt' => 'O valor deve ser maior que zero.',
            'valor.*' => 'Informe um valor válido, com até duas casas.',
            'ocorrido_em.required' => 'Informe a data e a hora.',
            'ocorrido_em.date' => 'A data informada é inválida.',
            'observacao.*' => 'A observação deve ter no máximo 500 caracteres.',
        ];
    }

    public function tipo(): TipoLancamentoCaixa
    {
        return TipoLancamentoCaixa::from($this->validated('tipo'));
    }

    public function valor(): string
    {
        return bcadd((string) $this->validated('valor'), '0', 2);
    }

    public function ocorrido_em(): Carbon
    {
        return $this->data_hora('ocorrido_em');
    }
}
