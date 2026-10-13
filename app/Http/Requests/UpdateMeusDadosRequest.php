<?php

namespace App\Http\Requests;

use App\Enums\Genero;
use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O próprio cliente só altera nome, gênero, e-mail e se aceita promoções.
 */
class UpdateMeusDadosRequest extends FormRequest
{
    use DadosCliente;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_cliente();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cliente = $this->user('clientes');

        // CPF, e-mail e data de nascimento: o cliente preenche uma única vez; depois, só o painel
        // altera, como no antigo (spec 007, research R-21). Telefone é o login: só o painel.
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'genero' => ['sometimes', 'required', Rule::enum(Genero::class)],
            'email' => $cliente->email ? ['prohibited'] : ['sometimes', ...$this->regras_email($cliente->id)],
            'cpf' => $cliente->cpf ? ['prohibited'] : ['sometimes', ...$this->regras_cpf($cliente->id)],
            'data_nascimento' => $cliente->data_nascimento
                ? ['prohibited']
                : ['sometimes', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'aceita_promocao' => ['sometimes', 'boolean'],
            'ddi' => ['prohibited'],
            'telefone' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensagem_atendimento = 'Este dado só pode ser alterado pelo atendimento.';

        return [
            ...$this->mensagens_dados_cliente(),
            'ddi.prohibited' => $mensagem_atendimento,
            'telefone.prohibited' => $mensagem_atendimento,
            'cpf.prohibited' => $mensagem_atendimento,
            'email.prohibited' => $mensagem_atendimento,
            'data_nascimento.prohibited' => $mensagem_atendimento,
        ];
    }
}
