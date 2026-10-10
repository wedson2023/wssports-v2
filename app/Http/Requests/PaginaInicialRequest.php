<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * Filtros da página inicial do site: mesmas regras e mesmo limite por IP da listagem pública. Um
 * filtro inválido não interrompe a página; volta ao padrão (contracts/paginas.md).
 */
class PaginaInicialRequest extends ListagemPublicaRequest
{
    /**
     * Página de jogos da tela (máximo de 100 da constituição).
     */
    private const POR_PAGINA = 50;

    /**
     * Filtros inválidos são ignorados, sem resposta 422.
     */
    protected function failedValidation(Validator $validator): void {}

    /**
     * Só os filtros válidos, com o tamanho de página fixo da tela.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return [
            ...$this->getValidatorInstance()->valid(),
            'por_pagina' => self::POR_PAGINA,
        ];
    }
}
