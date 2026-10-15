<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Resultado de jogo manual lançado pelo administrador (spec 009, FR-045 e contrato em
 * specs/009-apuracao-resultados/contracts/api.md). Usa os nomes do sistema.
 */
class ConfrontosResultadosRequest extends FormRequest
{
    private const PLACAR = 'Informe o placar final do jogo (0 a 99 gols para cada time).';

    private const TEMPOS = 'Informe os gols do 1º e do 2º tempo dos dois times ou deixe os quatro em branco.';

    private const ESCANTEIOS = 'Informe os escanteios dos dois times ou deixe os dois em branco.';

    private const GOL = 'Cada gol precisa da ordem (1 a 99, sem repetir) e do nome do jogador.';

    private const CAMPOS_TEMPOS = ['gols_primeiro_tempo_casa', 'gols_primeiro_tempo_fora', 'gols_segundo_tempo_casa', 'gols_segundo_tempo_fora'];

    private const CAMPOS_ESCANTEIOS = ['escanteios_casa', 'escanteios_fora'];

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
        $opcional = ['nullable', 'integer', 'between:0,99'];

        return [
            'placar_casa' => ['required', 'integer', 'between:0,99'],
            'placar_fora' => ['required', 'integer', 'between:0,99'],
            ...array_fill_keys([...self::CAMPOS_TEMPOS, ...self::CAMPOS_ESCANTEIOS], $opcional),
            'gols' => ['nullable', 'array', 'max:99'],
            'gols.*.ordem' => ['required', 'integer', 'between:1,99', 'distinct'],
            'gols.*.nome' => ['required', 'string', 'max:150'],
            // minuto com acréscimos, como o provedor envia ("45+1")
            'gols.*.minuto' => ['nullable', 'regex:/^\d{1,3}(\+\d{1,2})?$/'],
            'gols.*.tempo_jogo' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensagens = [];

        foreach (['placar_casa', 'placar_fora'] as $campo) {
            foreach (['required', 'integer', 'between'] as $regra) {
                $mensagens["{$campo}.{$regra}"] = self::PLACAR;
            }
        }

        foreach (self::CAMPOS_TEMPOS as $campo) {
            $mensagens["{$campo}.integer"] = self::TEMPOS;
            $mensagens["{$campo}.between"] = self::TEMPOS;
        }

        foreach (self::CAMPOS_ESCANTEIOS as $campo) {
            $mensagens["{$campo}.integer"] = self::ESCANTEIOS;
            $mensagens["{$campo}.between"] = self::ESCANTEIOS;
        }

        foreach (['gols.array', 'gols.max', 'gols.*.ordem.required', 'gols.*.ordem.integer', 'gols.*.ordem.between', 'gols.*.ordem.distinct',
            'gols.*.nome.required', 'gols.*.nome.string', 'gols.*.nome.max', 'gols.*.minuto.regex',
            'gols.*.tempo_jogo.string', 'gols.*.tempo_jogo.max'] as $chave) {
            $mensagens[$chave] = self::GOL;
        }

        return $mensagens;
    }

    /**
     * Gols de tempo: os quatro juntos; escanteios: os dois juntos.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->todos_ou_nenhum(self::CAMPOS_TEMPOS)) {
                    $validator->errors()->add('gols_primeiro_tempo_casa', self::TEMPOS);
                }

                if (! $this->todos_ou_nenhum(self::CAMPOS_ESCANTEIOS)) {
                    $validator->errors()->add('escanteios_casa', self::ESCANTEIOS);
                }
            },
        ];
    }

    /**
     * @param  list<string>  $campos
     */
    private function todos_ou_nenhum(array $campos): bool
    {
        $informados = count(array_filter($campos, fn (string $campo) => $this->input($campo) !== null));

        return $informados === 0 || $informados === count($campos);
    }
}
