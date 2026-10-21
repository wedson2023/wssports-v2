<?php

namespace App\Http\Requests;

use App\Support\FusoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Filtros das consultas do caixa (spec 017, contracts/api.md, research R-07): período em dias de
 * Brasília (até 366 dias, sem data futura), tipo de aposta, foco, busca, ordenação e paginação.
 */
class CaixaPeriodoRequest extends FormRequest
{
    private const DIAS_MAXIMOS = 366;

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller e o alcance pelo próprio controller
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
            'tipo' => ['nullable', 'in:todas,pre_jogo,ao_vivo'],
            'usuarios_id' => ['nullable', 'integer'],
            'busca' => ['nullable', 'string', 'max:100'],
            // `resultado` só vale na lista de clientes
            'ordenar' => ['nullable', 'in:nome,entradas,liquido,saldo_caixa,resultado'],
            'direcao' => ['nullable', 'in:asc,desc'],
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
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $de = CarbonImmutable::parse($this->input('de'));
            $ate = CarbonImmutable::parse($this->input('ate'));
            $hoje = CarbonImmutable::now(FusoSistema::FUSO)->startOfDay();

            if ($ate->greaterThan($hoje)) {
                $validador->errors()->add('ate', 'O período não pode terminar depois de hoje.');
            } elseif ($de->diffInDays($ate) > self::DIAS_MAXIMOS) {
                $validador->errors()->add('ate', 'O período deve ter no máximo 1 ano.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'de.required' => 'Informe a data inicial do período.',
            'ate.required' => 'Informe a data final do período.',
            '*.date_format' => 'A data informada é inválida.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'tipo.in' => 'O tipo deve ser todas, pre_jogo ou ao_vivo.',
            'usuarios_id.integer' => 'O usuário informado é inválido.',
            'busca.*' => 'A busca deve ter no máximo 100 caracteres.',
            'ordenar.in' => 'A ordenação informada é inválida.',
            'direcao.in' => 'A direção deve ser asc ou desc.',
            'page.*' => 'A página informada é inválida.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }

    /**
     * Início do primeiro dia e fim do último dia do período em Brasília, em UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function intervalo(): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $this->validated('de'), FusoSistema::FUSO)->startOfDay()->utc();
        $fim = Carbon::createFromFormat('Y-m-d', $this->validated('ate'), FusoSistema::FUSO)->endOfDay()->utc();

        return [$inicio, $fim];
    }

    public function tipo(): string
    {
        return $this->validated('tipo') ?? 'todas';
    }

    public function por_pagina(): int
    {
        return (int) ($this->validated('por_pagina') ?? 20);
    }
}
