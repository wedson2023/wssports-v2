<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Meus dados do afiliado (contracts/api §1.10, FR-056): só contato, endereço e Pix; nome, login,
 * e-mail, CPF e código só pela gestão.
 */
class UpdateMeusDadosAfiliadoRequest extends FormRequest
{
    use DadosAfiliado;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_afiliado();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $regras = Arr::only($this->regras_dados_afiliado('sometimes'), ['ddi', 'telefone', 'cidade', 'estado', 'endereco', 'pix_tipo_chave', 'pix_chave']);

        return [
            ...$regras,
            'pix_tipo_chave' => [...$regras['pix_tipo_chave'], 'required_with:pix_chave'],
            'pix_chave' => [...$regras['pix_chave'], 'required_with:pix_tipo_chave'],
            'nome' => ['prohibited'],
            'login' => ['prohibited'],
            'email' => ['prohibited'],
            'cpf' => ['prohibited'],
            'codigo' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $fale_com_a_banca = 'Para alterar, fale com a banca.';

        return [
            ...$this->mensagens_dados_afiliado(),
            'pix_tipo_chave.required_with' => 'Informe o tipo de chave Pix junto com a chave.',
            'pix_chave.required_with' => 'Informe a chave Pix junto com o tipo.',
            'nome.prohibited' => $fale_com_a_banca,
            'login.prohibited' => $fale_com_a_banca,
            'email.prohibited' => $fale_com_a_banca,
            'cpf.prohibited' => $fale_com_a_banca,
            'codigo.prohibited' => $fale_com_a_banca,
        ];
    }
}
