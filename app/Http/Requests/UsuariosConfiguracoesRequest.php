<?php

namespace App\Http\Requests;

use App\Models\UsuariosConfiguracoes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alteração das configurações dos vendedores de um alcance: só os campos enviados são gravados.
 */
class UsuariosConfiguracoesRequest extends FormRequest
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
        return [
            'usuarios_id' => ['required', 'integer', 'exists:usuarios,id'],
            'esportes_permitidos' => ["required_without_all:{$this->outros('esportes_permitidos')}", 'array', 'min:1'],
            'esportes_permitidos.*' => ['required', 'string', 'max:50', 'distinct'],
            'apostar_outros_esportes' => ['sometimes', 'boolean'],
            'ao_vivo_habilitado' => ['sometimes', 'boolean'],
            'minuto_limite_ao_vivo' => ['sometimes', 'integer', 'between:1,130'],
            'cotacao_maxima_ao_vivo' => ['sometimes', 'numeric', 'min:1', 'regex:/^\d+(\.\d{1,2})?$/'],
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
