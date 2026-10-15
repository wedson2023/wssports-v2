<?php

namespace App\Http\Requests;

use App\Enums\ModalidadeEncerramento;
use App\Enums\SituacaoEncerramento;
use App\Support\FusoSistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Filtros da listagem de encerramentos no painel (spec 010, FR-037): período no fuso do sistema
 * (padrão: hoje), cliente, modalidade e situação; no máximo 100 por página (constituição).
 */
class ListagemEncerramentosRequest extends FormRequest
{
    private const POR_PAGINA = 20;

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
            'data_inicial' => ['nullable', 'date_format:Y-m-d'],
            'data_final' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicial'],
            'clientes_id' => ['nullable', 'integer'],
            'modalidade' => ['nullable', Rule::enum(ModalidadeEncerramento::class)],
            'situacao' => ['nullable', Rule::enum(SituacaoEncerramento::class)],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data_inicial.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'data_final.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'data_final.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
            'clientes_id.integer' => 'O cliente informado é inválido.',
            'modalidade.enum' => 'A modalidade deve ser Total, Parcial ou Automático.',
            'situacao.enum' => 'A situação informada é inválida.',
            'pagina.min' => 'A página deve ser pelo menos 1.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Início e fim do período em UTC, a partir dos dias no fuso do sistema.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodo(): array
    {
        $inicial = $this->validated('data_inicial');
        $final = $this->validated('data_final') ?? $inicial;

        $inicio = $inicial !== null ? Carbon::parse($inicial, FusoSistema::FUSO)->startOfDay()->utc() : FusoSistema::inicio_do_dia();
        $fim = $final !== null ? Carbon::parse($final, FusoSistema::FUSO)->endOfDay()->utc() : FusoSistema::fim_do_dia_em(0);

        return [$inicio, $fim];
    }

    public function por_pagina(): int
    {
        return (int) ($this->validated('por_pagina') ?? self::POR_PAGINA);
    }
}
