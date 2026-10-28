<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros da tela Especiais do painel (spec 024, R-02): situação (com "Todas"), busca pelo nome,
 * lixeira e tamanho da página. Usados pela lista e, a busca, pelos cartões.
 */
class FiltrosEspeciaisPainelRequest extends FormRequest
{
    public const SITUACOES = ['Aguardando', 'Encerrado', 'Cancelado', 'Todas'];

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
        // filtros enviados vazios chegam como null e são ignorados
        return [
            'situacao' => ['nullable', Rule::in(self::SITUACOES)],
            'busca' => ['nullable', 'string', 'max:150'],
            'lixeira' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'situacao.in' => 'A situação informada é inválida.',
            'busca.string' => 'A busca deve ser um texto.',
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'lixeira.boolean' => 'O filtro lixeira deve ser verdadeiro ou falso.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    public function por_pagina(int $padrao = 20): int
    {
        return (int) ($this->validated('por_pagina') ?? $padrao);
    }

    /**
     * Filtros validados no formato que o ListagemEspeciaisPainel recebe.
     *
     * @return array{situacao: string, busca: ?string, lixeira: bool}
     */
    public function filtros(): array
    {
        $busca = trim((string) $this->validated('busca'));

        return [
            'situacao' => $this->validated('situacao') ?? 'Aguardando',
            'busca' => $busca !== '' ? $busca : null,
            'lixeira' => $this->boolean('lixeira'),
        ];
    }
}
