<?php

namespace App\Http\Requests;

use App\Enums\Genero;
use App\Http\Requests\Concerns\DadosCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edição do cliente pelo painel. Campos não enviados ficam como estão; saldos e situação
 * têm rotas próprias.
 */
class UpdateClientesRequest extends FormRequest
{
    use DadosCliente;

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_cliente();

        // ao trocar só o DDI, o telefone atual é revalidado para manter a unicidade por DDI + telefone
        if ($this->has('ddi') && ! $this->has('telefone')) {
            $this->merge(['telefone' => $this->route('cliente')->telefone]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $ddi = (string) ($this->input('ddi') ?? $cliente->ddi);

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'ddi' => ['sometimes', 'required', 'digits_between:1,3'],
            'telefone' => ['sometimes', 'required', ...$this->regras_telefone($ddi, $cliente->id)],
            'email' => ['sometimes', ...$this->regras_email($cliente->id)],
            'cpf' => ['sometimes', ...$this->regras_cpf($cliente->id)],
            'data_nascimento' => ['sometimes', 'required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'genero' => ['sometimes', 'required', Rule::enum(Genero::class)],
            'codigo_afiliado' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'required', 'confirmed', $this->regra_senha()],
            'saldo' => ['prohibited'],
            'saldo_promocao_esportes' => ['prohibited'],
            'saldo_promocao_cassino' => ['prohibited'],
            'ativo' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_dados_cliente(),
            'saldo.prohibited' => 'O saldo só muda por movimentação de saldo.',
            'saldo_promocao_esportes.prohibited' => 'O saldo só muda por movimentação de saldo.',
            'saldo_promocao_cassino.prohibited' => 'O saldo só muda por movimentação de saldo.',
            'ativo.prohibited' => 'Use a rota de situação para ativar ou desativar o cliente.',
        ];
    }
}
