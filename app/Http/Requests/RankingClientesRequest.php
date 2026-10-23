<?php

namespace App\Http\Requests;

use App\Services\ListagemAfiliados;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros do ranking de clientes (spec 019, FR-027, contracts/api.md §4): período (24 horas, 7, 30
 * ou 90 dias contados de agora, ou personalizado em dias de Brasília, até 366 dias), critério e
 * paginação.
 */
class RankingClientesRequest extends FormRequest
{
    public const PERIODOS = ['24h', '7d', '30d', '90d', 'personalizado'];

    public const CRITERIOS = ['depositos', 'valor_apostado', 'resultado', 'saques'];

    private const DIAS_MAXIMOS = 366;

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
            'periodo' => ['nullable', Rule::in(self::PERIODOS)],
            'de' => ['nullable', 'required_if:periodo,personalizado', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'required_if:periodo,personalizado', 'date_format:Y-m-d', 'after_or_equal:de'],
            'criterio' => ['nullable', Rule::in(self::CRITERIOS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty() || $this->periodo() !== 'personalizado') {
                return;
            }

            if (CarbonImmutable::parse($this->input('de'))->diffInDays(CarbonImmutable::parse($this->input('ate'))) >= self::DIAS_MAXIMOS) {
                $validador->errors()->add('ate', 'O período pode ter no máximo 366 dias.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo.in' => 'O período deve ser 24h, 7d, 30d, 90d ou personalizado.',
            'de.required_if' => 'Informe a data inicial do período.',
            'ate.required_if' => 'Informe a data final do período.',
            '*.date_format' => 'A data informada é inválida.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'criterio.in' => 'O critério deve ser depositos, valor_apostado, resultado ou saques.',
            'page.*' => 'A página informada é inválida.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    public function periodo(): string
    {
        return $this->validated('periodo') ?? '30d';
    }

    public function criterio(): string
    {
        return $this->validated('criterio') ?? 'depositos';
    }

    public function por_pagina(): int
    {
        return (int) ($this->validated('por_pagina') ?? 20);
    }

    public function pagina(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    /**
     * Início e fim em UTC: 24 horas ou N dias contados de agora; personalizado, os dias de Brasília.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function intervalo(): array
    {
        return self::intervalo_de($this->periodo(), $this->validated('de'), $this->validated('ate'));
    }

    /**
     * A mesma conta do intervalo, para a planilha do ranking (validada pelas mesmas regras).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function intervalo_de(string $periodo, ?string $de, ?string $ate): array
    {
        return match ($periodo) {
            '24h' => [now()->subDay(), now()],
            '7d' => [now()->subDays(7), now()],
            '90d' => [now()->subDays(90), now()],
            'personalizado' => ListagemAfiliados::periodo($de, $ate),
            default => [now()->subDays(30), now()],
        };
    }
}
