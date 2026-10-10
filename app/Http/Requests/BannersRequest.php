<?php

namespace App\Http\Requests;

use App\Models\Banners;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro e edição de banners (multipart): imagem obrigatória no cadastro e opcional na edição.
 */
class BannersRequest extends FormRequest
{
    private const IMAGEM_INVALIDA = 'Envie uma imagem jpg, png ou webp de até 4 MB.';

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
        $cadastro = ! $this->route('banner') instanceof Banners;

        return [
            'imagem' => [$cadastro ? 'required' : 'sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'ordem' => ['nullable', 'integer', 'between:0,999'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagem.required' => 'Envie a imagem do banner.',
            'imagem.image' => self::IMAGEM_INVALIDA,
            'imagem.mimes' => self::IMAGEM_INVALIDA,
            'imagem.max' => self::IMAGEM_INVALIDA,
            'link.url' => 'O link deve ser um endereço http ou https.',
            'link.max' => 'O link deve ter no máximo 500 caracteres.',
            'ordem.integer' => 'A ordem deve ser um número inteiro.',
            'ordem.between' => 'A ordem deve estar entre 0 e 999.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * Campos do banner sem a imagem (salva à parte).
     *
     * @return array<string, mixed>
     */
    public function dados_banner(): array
    {
        $dados = collect($this->validated())->except('imagem')->all();

        if (array_key_exists('ordem', $dados) && $dados['ordem'] === null) {
            $dados['ordem'] = 0;
        }

        return $dados;
    }
}
