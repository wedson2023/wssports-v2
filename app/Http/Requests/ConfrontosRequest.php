<?php

namespace App\Http\Requests;

use App\Enums\SituacaoConfronto;
use App\Models\Campeonatos;
use App\Support\CodigosCotacao;
use Carbon\CarbonTimeZone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Cadastro e edição de confronto manual: campeonato manual, data de início futura (informada no
 * fuso de quem cadastra e gravada em UTC) e ao menos uma cotação, todas ≥ 1,00.
 */
class ConfrontosRequest extends FormRequest
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
        $edicao = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'campeonatos_id' => ['required', 'integer', $this->campeonato_manual()],
            'time_casa' => ['required', 'string', 'max:150'],
            'time_fora' => ['required', 'string', 'max:150'],
            'escudo_casa' => ['nullable', 'string', 'max:255'],
            'escudo_fora' => ['nullable', 'string', 'max:255'],
            'esporte' => ['required', 'string', 'max:50'],
            'fuso_horario' => ['nullable', 'regex:/^[+-](0\d|1[0-4]):[0-5]\d$/'],
            'data_inicio' => ['required', 'date_format:Y-m-d H:i', $this->data_futura()],
            'cotacoes' => ['required', 'array', 'min:1', $this->cotacoes_validas()],
            'situacao' => [$edicao ? 'nullable' : 'prohibited', Rule::in(array_column(SituacaoConfronto::editaveis_manualmente(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campeonatos_id.required' => 'O campeonato é obrigatório.',
            'campeonatos_id.integer' => 'O campeonato deve ser um número inteiro.',
            'time_casa.required' => 'O time da casa é obrigatório.',
            'time_casa.max' => 'O time da casa deve ter no máximo 150 caracteres.',
            'time_fora.required' => 'O time de fora é obrigatório.',
            'time_fora.max' => 'O time de fora deve ter no máximo 150 caracteres.',
            'escudo_casa.max' => 'O escudo da casa deve ter no máximo 255 caracteres.',
            'escudo_fora.max' => 'O escudo de fora deve ter no máximo 255 caracteres.',
            'esporte.required' => 'O esporte é obrigatório.',
            'esporte.max' => 'O esporte deve ter no máximo 50 caracteres.',
            'fuso_horario.regex' => 'O fuso horário deve estar no formato ±HH:MM (ex.: -03:00).',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_inicio.date_format' => 'A data de início deve estar no formato AAAA-MM-DD HH:MM.',
            'cotacoes.required' => 'Informe ao menos uma cotação.',
            'cotacoes.array' => 'Cotações deve ser um objeto com os códigos de cotação.',
            'cotacoes.min' => 'Informe ao menos uma cotação.',
            'situacao.prohibited' => 'A situação só pode ser alterada na edição.',
            'situacao.in' => 'A situação deve ser Aguardando, Adiado ou Cancelado.',
        ];
    }

    /**
     * Data de início convertida para UTC.
     */
    public function data_inicio_utc(): string
    {
        return $this->data_no_fuso()->utc()->format('Y-m-d H:i:s');
    }

    /**
     * Cotações só com os valores numéricos, com 2 casas.
     *
     * @return array<string, float>
     */
    public function cotacoes(): array
    {
        return array_map(fn ($valor) => round((float) $valor, 2), $this->validated('cotacoes'));
    }

    private function data_no_fuso(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $this->input('data_inicio'), new CarbonTimeZone($this->input('fuso_horario') ?: '-03:00'));
    }

    private function campeonato_manual(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            if (! Campeonatos::where('id', $valor)->where('manual', true)->exists()) {
                $falhar('O campeonato deve ser um campeonato manual existente.');
            }
        };
    }

    private function data_futura(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            try {
                if ($this->data_no_fuso()->lte(now())) {
                    $falhar('A data de início deve ser futura.');
                }
            } catch (Throwable) {
                // o formato inválido já é informado pela regra date_format
            }
        };
    }

    private function cotacoes_validas(): Closure
    {
        return function (string $atributo, mixed $cotacoes, Closure $falhar) {
            if (! is_array($cotacoes) || array_is_list($cotacoes)) {
                $falhar('Cotações deve ser um objeto com os códigos de cotação.');

                return;
            }

            foreach ($cotacoes as $codigo => $valor) {
                if (! CodigosCotacao::e_cotacao((string) $codigo)) {
                    $falhar("O código {$codigo} não existe.");

                    return;
                }

                if (! is_numeric($valor) || (float) $valor < 1 || ! preg_match('/^\d+(\.\d{1,2})?$/', (string) $valor)) {
                    $falhar("A cotação de {$codigo} deve ser maior ou igual a 1,00, com até 2 casas decimais.");

                    return;
                }
            }
        };
    }
}
