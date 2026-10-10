<?php

namespace App\Http\Requests;

use App\Models\EspeciaisOpcoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edição de uma opção de categoria especial. O nome é único dentro da categoria.
 */
class EspeciaisOpcoesRequest extends FormRequest
{
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
        $opcao = $this->route('opcao');
        $cadastro = ! $opcao instanceof EspeciaisOpcoes;

        return [
            'nome' => [
                $cadastro ? 'required' : 'sometimes',
                'string',
                'max:150',
                Rule::unique('especiais_opcoes', 'nome')
                    ->where('especiais_id', $this->route('especial')->id)
                    ->whereNull('deleted_at')
                    ->ignore($opcao?->id),
            ],
            'cotacao' => [$cadastro ? 'required' : 'sometimes', 'numeric', 'regex:/^\d{1,6}(\.\d{1,2})?$/', 'min:1.01'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da opção.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'nome.unique' => 'Já existe uma opção com este nome na categoria.',
            'cotacao.required' => 'Informe a cotação da opção.',
            'cotacao.numeric' => 'A cotação deve ser um número.',
            'cotacao.regex' => 'A cotação deve ter no máximo 2 casas decimais.',
            'cotacao.min' => 'A cotação deve ser de pelo menos 1.01.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }
}
