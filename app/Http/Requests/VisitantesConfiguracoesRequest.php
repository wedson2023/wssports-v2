<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações de quem não está logado.
 */
class VisitantesConfiguracoesRequest extends FormRequest
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
            'esportes_permitidos' => ['required', 'array', 'min:1'],
            'esportes_permitidos.*' => ['required', 'string', 'max:50', 'distinct'],
            'apostar_outros_esportes' => ['required', 'boolean'],
            'ao_vivo_habilitado' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'esportes_permitidos.required' => 'Informe ao menos um esporte permitido.',
            'esportes_permitidos.array' => 'Os esportes permitidos devem ser uma lista.',
            'esportes_permitidos.min' => 'Informe ao menos um esporte permitido.',
            'esportes_permitidos.*.required' => 'O nome do esporte é obrigatório.',
            'esportes_permitidos.*.max' => 'O nome do esporte deve ter no máximo 50 caracteres.',
            'esportes_permitidos.*.distinct' => 'Os esportes permitidos não podem se repetir.',
            'apostar_outros_esportes.required' => 'O campo apostar outros esportes é obrigatório.',
            'apostar_outros_esportes.boolean' => 'O campo apostar outros esportes deve ser verdadeiro ou falso.',
            'ao_vivo_habilitado.required' => 'O campo ao vivo habilitado é obrigatório.',
            'ao_vivo_habilitado.boolean' => 'O campo ao vivo habilitado deve ser verdadeiro ou falso.',
        ];
    }
}
