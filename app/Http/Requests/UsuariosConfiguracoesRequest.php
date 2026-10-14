<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfiguracoesAposta;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alteração das configurações dos vendedores de um alcance: só os campos enviados são gravados.
 */
class UsuariosConfiguracoesRequest extends FormRequest
{
    use ConfiguracoesAposta;

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->converter_data_travamento();
    }

    /**
     * Regras de aposta do vendedor (spec 004).
     *
     * @return list<string>
     */
    private function campos_aposta(): array
    {
        return [
            ...UsuariosConfiguracoes::CAMPOS_APOSTA,
            ...UsuariosConfiguracoes::COMISSOES_PRE_JOGO,
            ...UsuariosConfiguracoes::COMISSOES_AO_VIVO,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuarios_id' => ['required', 'integer', 'exists:usuarios,id'],
            'esportes_permitidos' => ["required_without_all:{$this->outros('esportes_permitidos')}", 'array', 'min:1'],
            'esportes_permitidos.*' => ['required', 'string', 'max:50', 'distinct'],
            'apostar_outros_esportes' => ['sometimes', 'boolean'],
            'ao_vivo_habilitado' => ['sometimes', 'boolean'],
            'minuto_limite_ao_vivo' => ['sometimes', 'integer', 'between:1,130'],
            'cotacao_maxima_ao_vivo' => ['sometimes', 'numeric', 'min:1', 'regex:/^\d+(\.\d{1,2})?$/'],
            ...$this->regras_aposta($this->campos_aposta()),
            // Criar Aposta do vendedor (spec 008)
            'criar_aposta_habilitado' => ['sometimes', 'boolean'],
            'desconto_criar_aposta' => ['sometimes', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'between:0,100'],
            // o alvo pode ser um alcance com vários vendedores: mínimo ≤ máximo só com os dois no
            // pedido, como em quantidade_minima_opcoes
            'quantidade_minima_criar_aposta' => [
                'sometimes', 'integer', 'between:2,20',
                ...($this->has('quantidade_maxima_criar_aposta') ? ['lte:quantidade_maxima_criar_aposta'] : []),
            ],
            'quantidade_maxima_criar_aposta' => ['sometimes', 'integer', 'between:2,20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuarios_id.required' => 'Informe o usuário (supervisor, gerente ou vendedor) a alterar.',
            'usuarios_id.exists' => 'Usuário não encontrado.',
            'esportes_permitidos.required_without_all' => 'Informe ao menos um campo de configuração.',
            'esportes_permitidos.array' => 'Os esportes permitidos devem ser uma lista.',
            'esportes_permitidos.min' => 'Informe ao menos um esporte permitido.',
            'esportes_permitidos.*.required' => 'O nome do esporte é obrigatório.',
            'esportes_permitidos.*.max' => 'O nome do esporte deve ter no máximo 50 caracteres.',
            'esportes_permitidos.*.distinct' => 'Os esportes permitidos não podem se repetir.',
            'apostar_outros_esportes.boolean' => 'O campo apostar outros esportes deve ser verdadeiro ou falso.',
            'ao_vivo_habilitado.boolean' => 'O campo ao vivo habilitado deve ser verdadeiro ou falso.',
            'minuto_limite_ao_vivo.integer' => 'O minuto limite do ao vivo deve ser um número inteiro.',
            'minuto_limite_ao_vivo.between' => 'O minuto limite do ao vivo deve estar entre 1 e 130.',
            'cotacao_maxima_ao_vivo.numeric' => 'A cotação máxima do ao vivo deve ser um número.',
            'cotacao_maxima_ao_vivo.min' => 'A cotação máxima do ao vivo deve ser maior ou igual a 1,00.',
            'cotacao_maxima_ao_vivo.regex' => 'A cotação máxima do ao vivo deve ter até 2 casas decimais.',
            ...$this->mensagens_aposta($this->campos_aposta()),
            'criar_aposta_habilitado.boolean' => 'O campo criar_aposta_habilitado deve ser verdadeiro ou falso.',
            'desconto_criar_aposta.numeric' => 'O desconto do Criar Aposta deve ser de 0 a 100%.',
            'desconto_criar_aposta.regex' => 'O desconto do Criar Aposta deve ter até 2 casas decimais.',
            'desconto_criar_aposta.between' => 'O desconto do Criar Aposta deve ser de 0 a 100%.',
            'quantidade_minima_criar_aposta.integer' => 'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
            'quantidade_minima_criar_aposta.between' => 'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
            'quantidade_minima_criar_aposta.lte' => 'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
            'quantidade_maxima_criar_aposta.integer' => 'O máximo de palpites do Criar Aposta deve ser de 2 a 20 e não pode ser menor que o mínimo.',
            'quantidade_maxima_criar_aposta.between' => 'O máximo de palpites do Criar Aposta deve ser de 2 a 20 e não pode ser menor que o mínimo.',
        ];
    }

    /**
     * Campos de configuração enviados (sem o usuário).
     *
     * @return array<string, mixed>
     */
    public function campos(): array
    {
        return collect($this->validated())->only(UsuariosConfiguracoes::CAMPOS)->all();
    }

    private function outros(string $campo): string
    {
        return implode(',', array_diff(UsuariosConfiguracoes::CAMPOS, [$campo]));
    }
}
