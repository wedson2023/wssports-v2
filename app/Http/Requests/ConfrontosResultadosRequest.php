<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Resultado de jogo lançado à mão pelo Admin (spec 009, FR-045, alterado pela spec 023, R-12;
 * contrato em specs/009-apuracao-resultados/contracts/api.md). Como no sistema antigo: placar,
 * gols do 1º e do 2º tempo e escanteios obrigatórios, e a soma dos tempos igual ao placar. Usa os
 * nomes do sistema.
 */
class ConfrontosResultadosRequest extends FormRequest
{
    private const PLACAR = 'Informe o placar final do jogo (0 a 99 gols para cada time).';

    private const TEMPOS = 'Informe os gols do 1º e do 2º tempo dos dois times (0 a 99).';

    private const ESCANTEIOS = 'Informe os escanteios dos dois times (0 a 99).';

    private const SOMA = 'A soma dos gols dos times não bate com os gols dos tempos.';

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
        $obrigatorio = ['required', 'integer', 'between:0,99'];

        return [
            'placar_casa' => $obrigatorio,
            'placar_fora' => $obrigatorio,
            ...array_fill_keys([...self::CAMPOS_TEMPOS, ...self::CAMPOS_ESCANTEIOS], $obrigatorio),
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
        $grupos = [
            self::PLACAR => ['placar_casa', 'placar_fora'],
            self::TEMPOS => self::CAMPOS_TEMPOS,
            self::ESCANTEIOS => self::CAMPOS_ESCANTEIOS,
        ];

        foreach ($grupos as $mensagem => $campos) {
            foreach ($campos as $campo) {
                foreach (['required', 'integer', 'between'] as $regra) {
                    $mensagens["{$campo}.{$regra}"] = $mensagem;
                }
            }
        }

        foreach (['gols.array', 'gols.max', 'gols.*.ordem.required', 'gols.*.ordem.integer', 'gols.*.ordem.between', 'gols.*.ordem.distinct',
            'gols.*.nome.required', 'gols.*.nome.string', 'gols.*.nome.max', 'gols.*.minuto.regex',
            'gols.*.tempo_jogo.string', 'gols.*.tempo_jogo.max'] as $chave) {
            $mensagens[$chave] = self::GOL;
        }

        return $mensagens;
    }

    /**
     * Soma dos gols do 1º e do 2º tempo igual à soma do placar final (regra do antigo).
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

                $gols_placar = (int) $this->input('placar_casa') + (int) $this->input('placar_fora');
                $gols_tempos = array_sum(array_map(fn (string $campo) => (int) $this->input($campo), self::CAMPOS_TEMPOS));

                if ($gols_placar !== $gols_tempos) {
                    $validator->errors()->add('placar_casa', self::SOMA);
                }
            },
        ];
    }
}
