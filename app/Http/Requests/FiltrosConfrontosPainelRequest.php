<?php

namespace App\Http\Requests;

use App\Enums\SituacaoConfronto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros da lista de confrontos do painel (spec 023, data-model §4): os da spec 003 mais o dia de
 * 30 dias atrás até depois de amanhã (atalho ou data) e o tipo Todos / Pré-jogo / Ao vivo. Usados
 * pela lista, pelos cartões e pela aba Mais apostados.
 */
class FiltrosConfrontosPainelRequest extends FormRequest
{
    public const DIAS = ['ontem', 'hoje', 'amanha', 'depois_de_amanha'];

    public const TIPOS = ['todos', 'pre_jogo', 'ao_vivo'];

    // janela de dias que a tela consulta, a partir de hoje no fuso de quem vê
    private const DIAS_ATRAS = 30;

    private const DIAS_A_FRENTE = 2;

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
            'busca' => ['nullable', 'string', 'max:150'],
            'campeonatos_id' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'],
            'manual' => ['nullable', 'boolean'],
            'esporte' => ['nullable', 'string', 'max:50'],
            'situacao' => ['nullable', Rule::enum(SituacaoConfronto::class)],
            'dia' => ['nullable', Rule::in(self::DIAS)],
            'data' => ['nullable', 'date_format:Y-m-d'],
            'tipo' => ['nullable', Rule::in(self::TIPOS)],
            'fuso_horario' => ['nullable', 'regex:/^[+-](0\d|1[0-4]):[0-5]\d$/'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty() || ! $this->filled('data')) {
                return;
            }

            $hoje = CarbonImmutable::now($this->fuso())->startOfDay();
            $data = CarbonImmutable::createFromFormat('Y-m-d', $this->input('data'), $this->fuso())->startOfDay();

            if ($data->lessThan($hoje->subDays(self::DIAS_ATRAS)) || $data->greaterThan($hoje->addDays(self::DIAS_A_FRENTE))) {
                $validador->errors()->add('data', 'A data deve estar entre os últimos 30 dias e depois de amanhã.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'busca.max' => 'A busca deve ter no máximo 150 caracteres.',
            'campeonatos_id.integer' => 'O campeonato deve ser um número inteiro.',
            'ativo.boolean' => 'O filtro ativo deve ser verdadeiro ou falso.',
            'manual.boolean' => 'O filtro manual deve ser verdadeiro ou falso.',
            'esporte.max' => 'O esporte deve ter no máximo 50 caracteres.',
            'situacao.enum' => 'A situação informada é inválida.',
            'dia.in' => 'O dia deve ser ontem, hoje, amanha ou depois_de_amanha.',
            'data.date_format' => 'A data informada é inválida.',
            'tipo.in' => 'O tipo deve ser todos, pre_jogo ou ao_vivo.',
            'fuso_horario.regex' => 'O fuso horário deve estar no formato ±HH:MM (ex.: -03:00).',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    public function por_pagina(int $padrao = 20): int
    {
        return (int) ($this->validated('por_pagina') ?? $padrao);
    }

    /**
     * Filtros validados no formato que o ListagemConfrontosPainel recebe; a data vence o atalho.
     *
     * @return array{busca: ?string, campeonatos_id: ?int, ativo: ?bool, manual: ?bool, esporte: ?string, situacao: ?string, dia: ?string, data: ?string, tipo: string, fuso_horario: string}
     */
    public function filtros(): array
    {
        $busca = trim((string) $this->validated('busca'));

        return [
            'busca' => $busca !== '' ? $busca : null,
            'campeonatos_id' => $this->filled('campeonatos_id') ? (int) $this->validated('campeonatos_id') : null,
            'ativo' => $this->filled('ativo') ? $this->boolean('ativo') : null,
            'manual' => $this->filled('manual') ? $this->boolean('manual') : null,
            'esporte' => $this->validated('esporte'),
            'situacao' => $this->validated('situacao'),
            'dia' => $this->filled('data') ? null : $this->validated('dia'),
            'data' => $this->validated('data'),
            'tipo' => $this->validated('tipo') ?? 'todos',
            'fuso_horario' => $this->fuso(),
        ];
    }

    private function fuso(): string
    {
        $fuso = $this->input('fuso_horario');

        return is_string($fuso) && preg_match('/^[+-](0\d|1[0-4]):[0-5]\d$/', $fuso) === 1 ? $fuso : '-03:00';
    }
}
