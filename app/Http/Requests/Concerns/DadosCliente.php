<?php

namespace App\Http\Requests\Concerns;

use App\Rules\CpfValido;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Normalização e regras dos dados do cliente, compartilhadas pelo cadastro público,
 * pela edição no painel e pela restauração.
 */
trait DadosCliente
{
    /**
     * Telefone, CPF e DDI só com dígitos; e-mail em minúsculas; vazios viram nulo.
     */
    protected function normalizar_dados_cliente(): void
    {
        $normalizados = [];

        foreach (['ddi', 'telefone', 'cpf'] as $campo) {
            if ($this->has($campo) && $this->input($campo) !== null) {
                $normalizados[$campo] = preg_replace('/\D/', '', (string) $this->input($campo));
            }
        }

        if ($this->has('email') && $this->input('email') !== null) {
            $normalizados['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        foreach (['cpf', 'email'] as $campo) {
            if (($normalizados[$campo] ?? null) === '') {
                $normalizados[$campo] = null;
            }
        }

        $this->merge($normalizados);
    }

    /**
     * DDI e telefone só com dígitos, com DDI 55 quando não informado (login e recuperação de senha).
     */
    protected function normalizar_ddi_telefone(): void
    {
        $this->merge([
            'ddi' => preg_replace('/\D/', '', (string) $this->input('ddi')) ?: '55',
            'telefone' => preg_replace('/\D/', '', (string) $this->input('telefone')),
        ]);
    }

    /**
     * Telefone com DDI 55 tem 10 ou 11 dígitos; nos demais países, de 4 a 14.
     *
     * @return list<mixed>
     */
    protected function regras_telefone(string $ddi, ?int $ignorar_id = null): array
    {
        return [
            'string',
            $ddi === '55' ? 'digits_between:10,11' : 'digits_between:4,14',
            Rule::unique('clientes', 'telefone')->where('ddi', $ddi)->ignore($ignorar_id),
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function regras_cpf(?int $ignorar_id = null): array
    {
        return ['nullable', 'string', new CpfValido, Rule::unique('clientes', 'cpf')->ignore($ignorar_id)];
    }

    /**
     * @return list<mixed>
     */
    protected function regras_email(?int $ignorar_id = null): array
    {
        return ['nullable', 'email', 'max:150', Rule::unique('clientes', 'email')->ignore($ignorar_id)];
    }

    protected function regra_senha(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    /**
     * @return array<string, string>
     */
    protected function mensagens_senha(): array
    {
        $regra = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';

        return [
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => $regra,
            'password.letters' => $regra,
            'password.numbers' => $regra,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensagens_dados_cliente(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'ddi.digits_between' => 'O DDI deve ter de 1 a 3 dígitos.',
            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.digits_between' => 'O telefone informado é inválido.',
            'telefone.unique' => 'O telefone já está cadastrado.',
            'email.email' => 'O e-mail informado é inválido.',
            'email.max' => 'O e-mail deve ter no máximo 150 caracteres.',
            'email.unique' => 'O e-mail já está cadastrado.',
            'cpf.unique' => 'O CPF já está cadastrado.',
            ...$this->mensagens_senha(),
            'data_nascimento.required' => 'A data de nascimento é obrigatória.',
            'data_nascimento.date' => 'A data de nascimento é inválida.',
            'data_nascimento.before_or_equal' => 'É preciso ter 18 anos ou mais.',
            'genero.required' => 'O gênero é obrigatório.',
            'genero.enum' => 'O gênero informado é inválido.',
            'codigo_afiliado.max' => 'O código de afiliado deve ter no máximo 50 caracteres.',
            'aceita_promocao.boolean' => 'O campo aceita promoção deve ser verdadeiro ou falso.',
        ];
    }
}
