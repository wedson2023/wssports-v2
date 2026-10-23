<?php

namespace App\Http\Requests;

use App\Enums\Funcao;
use App\Enums\Genero;
use App\Http\Requests\Concerns\DadosCliente;
use App\Models\Afiliados;
use App\Services\AlcanceClientes;
use Closure;
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
            // afiliado que indicou o cliente (spec 012); só a gestão altera. Supervisor e Gerente só
            // escolhem afiliado da rede e não deixam o cliente sem afiliado (spec 019, FR-003b)
            'afiliados_id' => $this->user()?->funcao() === Funcao::Admin
                ? ['sometimes', 'nullable', 'integer', Rule::exists('afiliados', 'id')->whereNull('deleted_at')]
                : ['sometimes', 'required', 'integer', Rule::exists('afiliados', 'id')->whereNull('deleted_at'), $this->regra_afiliado_da_rede()],
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
            'afiliados_id.required' => 'Escolha um afiliado da sua rede.',
        ];
    }

    /**
     * O afiliado escolhido tem como gerente responsável alguém da rede do usuário.
     */
    private function regra_afiliado_da_rede(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            $gerentes = app(AlcanceClientes::class)->ids_gerentes($this->user()) ?? [];

            if (! Afiliados::whereKey($valor)->whereIn('usuarios_id', $gerentes)->exists()) {
                $falhar('Escolha um afiliado da sua rede.');
            }
        };
    }
}
