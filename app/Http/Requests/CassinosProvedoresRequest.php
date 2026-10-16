<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alteração de um provedor do cassino no painel (spec 011, FR-030, FR-031).
 */
class CassinosProvedoresRequest extends FormRequest
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
            'nome' => ['sometimes', 'string', 'between:2,100'],
            'ativo' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'between:0,65535'],
            'porcentagem_comissao' => ['sometimes', 'numeric', 'between:0,100', 'decimal:0,2'],
            'imagem' => ['sometimes', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.*' => 'O nome deve ter de 2 a 100 caracteres.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
            'ordem.*' => 'A ordem deve ser um número de 0 a 65535.',
            'porcentagem_comissao.*' => 'A porcentagem de comissão deve ser de 0 a 100, com até duas casas.',
            'imagem.*' => 'A imagem deve ser PNG, JPG ou WEBP de até 1 MB.',
        ];
    }
}
