<?php

namespace App\Http\Requests;

use App\Models\Especiais;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edição de categorias especiais. No cadastro vêm também as opções (no mínimo duas).
 * A data limite sem fuso é lida no horário de Brasília (-03:00) e gravada em UTC.
 */
class EspeciaisRequest extends FormRequest
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
        $especial = $this->route('especial');
        $cadastro = ! $especial instanceof Especiais;

        return [
            'nome' => [
                $cadastro ? 'required' : 'sometimes',
                'string',
                'max:150',
                Rule::unique('especiais', 'nome')->whereNull('deleted_at')->ignore($especial?->id),
            ],
            'data_limite' => [$cadastro ? 'required' : 'sometimes', 'date'],
            'ativo' => ['sometimes', 'boolean'],
            'opcoes' => $cadastro ? ['required', 'array', 'min:2', 'max:100'] : ['prohibited'],
            'opcoes.*.nome' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'opcoes.*.cotacao' => ['required', 'numeric', 'regex:/^\d{1,6}(\.\d{1,2})?$/', 'min:1.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da categoria.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 150 caracteres.',
            'nome.unique' => 'Já existe uma categoria especial com este nome.',
            'data_limite.required' => 'Informe a data limite para apostar.',
            'data_limite.date' => 'A data limite é inválida.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'opcoes.required' => 'Informe as opções da categoria.',
            'opcoes.array' => 'As opções devem ser uma lista.',
            'opcoes.min' => 'Informe pelo menos 2 opções.',
            'opcoes.max' => 'Informe no máximo 100 opções.',
            'opcoes.prohibited' => 'As opções são alteradas pelas rotas de opções da categoria.',
            'opcoes.*.nome.required' => 'Informe o nome de cada opção.',
            'opcoes.*.nome.string' => 'O nome da opção deve ser um texto.',
            'opcoes.*.nome.max' => 'O nome da opção deve ter no máximo 150 caracteres.',
            'opcoes.*.nome.distinct' => 'Há opções com o mesmo nome.',
            'opcoes.*.cotacao.required' => 'Informe a cotação de cada opção.',
            'opcoes.*.cotacao.numeric' => 'A cotação deve ser um número.',
            'opcoes.*.cotacao.regex' => 'A cotação deve ter no máximo 2 casas decimais.',
            'opcoes.*.cotacao.min' => 'A cotação deve ser de pelo menos 1.01.',
        ];
    }

    /**
     * Dados da categoria, com a data limite em UTC.
     *
     * @return array<string, mixed>
     */
    public function dados_categoria(): array
    {
        $dados = collect($this->validated())->only(['nome', 'data_limite', 'ativo'])->all();

        if (isset($dados['data_limite'])) {
            $dados['data_limite'] = Carbon::parse($dados['data_limite'], '-03:00')->utc();
        }

        return $dados;
    }
}
