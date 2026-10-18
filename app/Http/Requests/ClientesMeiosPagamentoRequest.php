<?php

namespace App\Http\Requests;

use App\Enums\TipoChavePix;
use App\Enums\TipoConta;
use App\Enums\TipoMeioPagamento;
use App\Http\Requests\Concerns\ChavePix;
use App\Rules\CnpjValido;
use App\Models\ClientesMeiosPagamento;
use App\Rules\CpfValido;
use BackedEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Meio de pagamento (Pix ou transferência bancária), usado pela área do cliente e pelo painel.
 */
class ClientesMeiosPagamentoRequest extends FormRequest
{
    use ChavePix;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (['agencia', 'conta', 'titular_documento', 'banco_codigo'] as $campo) {
            if ($this->filled($campo)) {
                $normalizados[$campo] = preg_replace('/\D/', '', (string) $this->input($campo));
            }
        }

        if ($this->filled('pix_chave')) {
            $normalizados['pix_chave'] = $this->normalizar_chave_pix((string) $this->input('pix_chave'));
        }

        $this->merge($normalizados);

        // na edição, os campos não enviados são completados com os gravados, para validar o
        // registro resultante (ex.: só marcar como principal)
        $meio = $this->route('meio_pagamento');

        if ($meio instanceof ClientesMeiosPagamento && ! $this->has('tipo')) {
            $gravados = collect($meio->only($meio->getFillable()))
                ->map(fn (mixed $valor) => $valor instanceof BackedEnum ? $valor->value : $valor)
                ->reject(fn (mixed $valor, string $campo) => $this->has($campo));

            $this->merge($gravados->all());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pix = 'required_if:tipo,'.TipoMeioPagamento::Pix->value;
        $transferencia = 'required_if:tipo,'.TipoMeioPagamento::TransferênciaBancária->value;
        $obrigatoria = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'tipo' => [$obrigatoria, Rule::enum(TipoMeioPagamento::class)],
            'principal' => ['sometimes', 'boolean'],
            'pix_nome_titular' => ['nullable', $pix, 'string', 'max:150'],
            'pix_tipo_chave' => ['nullable', $pix, Rule::enum(TipoChavePix::class)],
            'pix_chave' => ['nullable', $pix, 'string', 'max:150', $this->regra_chave_pix()],
            'banco_codigo' => ['nullable', $transferencia, 'digits:3'],
            'banco_nome' => ['nullable', $transferencia, 'string', 'max:100'],
            'agencia' => ['nullable', $transferencia, 'digits_between:1,10'],
            'conta' => ['nullable', $transferencia, 'digits_between:1,20'],
            'conta_digito' => ['nullable', $transferencia, 'string', 'max:2'],
            'conta_tipo' => ['nullable', $transferencia, Rule::enum(TipoConta::class)],
            'titular_nome' => ['nullable', $transferencia, 'string', 'max:150'],
            'titular_documento' => ['nullable', $transferencia, $this->regra_documento()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'O tipo do meio de pagamento é obrigatório.',
            'tipo.enum' => 'O tipo deve ser Pix ou Transferência bancária.',
            'principal.boolean' => 'O campo principal deve ser verdadeiro ou falso.',
            '*.required_if' => 'O campo :attribute é obrigatório para este tipo de meio de pagamento.',
            '*.max' => 'O campo :attribute é longo demais.',
            'pix_tipo_chave.enum' => 'O tipo de chave Pix informado é inválido.',
            'banco_codigo.digits' => 'O código do banco deve ter 3 dígitos.',
            'agencia.digits_between' => 'A agência deve ter só dígitos, até 10.',
            'conta.digits_between' => 'A conta deve ter só dígitos, até 20.',
            'conta_tipo.enum' => 'O tipo de conta deve ser Corrente ou Poupança.',
        ];
    }

    /**
     * Documento do titular: CPF (11 dígitos) ou CNPJ (14 dígitos) válido.
     */
    private function regra_documento(): Closure
    {
        return function (string $atributo, mixed $documento, Closure $falhar) {
            if (! CpfValido::valido((string) $documento) && ! CnpjValido::valido((string) $documento)) {
                $falhar('O documento do titular deve ser um CPF ou CNPJ válido.');
            }
        };
    }
}
