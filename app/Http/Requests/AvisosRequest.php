<?php

namespace App\Http\Requests;

use App\Models\Avisos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Cadastro e edição de avisos (multipart): imagem obrigatória no cadastro e opcional na edição.
 * Datas sem fuso são lidas no horário de Brasília (-03:00) e gravadas em UTC.
 */
class AvisosRequest extends FormRequest
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
        $cadastro = ! $this->route('aviso') instanceof Avisos;

        return [
            'imagem' => [$cadastro ? 'required' : 'sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'titulo' => ['nullable', 'string', 'max:100'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'inicio_em' => ['nullable', 'date'],
            'fim_em' => ['nullable', 'date', 'after_or_equal:inicio_em'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagem.required' => 'Envie a imagem do aviso.',
            'imagem.image' => 'Envie uma imagem jpg, png ou webp de até 2 MB.',
            'imagem.mimes' => 'Envie uma imagem jpg, png ou webp de até 2 MB.',
            'imagem.max' => 'Envie uma imagem jpg, png ou webp de até 2 MB.',
            'titulo.string' => 'O título deve ser um texto.',
            'titulo.max' => 'O título deve ter no máximo 100 caracteres.',
            'link.url' => 'O link deve ser um endereço http ou https.',
            'link.max' => 'O link deve ter no máximo 500 caracteres.',
            'inicio_em.date' => 'O início da exibição é inválido.',
            'fim_em.date' => 'O fim da exibição é inválido.',
            'fim_em.after_or_equal' => 'O fim da exibição deve ser igual ou depois do início.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * Campos do aviso (sem a imagem, salva à parte), com as datas em UTC.
     *
     * @return array<string, mixed>
     */
    public function dados_aviso(): array
    {
        $dados = collect($this->validated())->except('imagem')->all();

        foreach (['inicio_em', 'fim_em'] as $campo) {
            if (! empty($dados[$campo])) {
                $dados[$campo] = Carbon::parse($dados[$campo], '-03:00')->utc();
            }
        }

        return $dados;
    }
}
