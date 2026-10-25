<?php

namespace App\Http\Requests;

use App\Models\Configuracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Intervalos de sorteio de "ambas marcam" e "ambas não marcam" (antigos "ADD ambas" e "ADD não
 * ambas"), editados na aba Operação (spec 021, FR-029). O mínimo de cada par não pode passar do
 * máximo, contando o valor já gravado quando só um dos dois vem.
 */
class ConfiguracoesSorteioCotacoesRequest extends FormRequest
{
    private const VALOR = 'regex:/^\d+(\.\d{1,2})?$/';

    /**
     * Pares mínimo/máximo e a mensagem de cada um.
     */
    private const PARES = [
        'sorteio_ambas_marcam' => 'O mínimo de "ambas marcam" não pode ser maior que o máximo.',
        'sorteio_ambas_nao_marcam' => 'O mínimo de "ambas não marcam" não pode ser maior que o máximo.',
    ];

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
        $regra = ['sometimes', 'numeric', self::VALOR, 'between:1.01,50'];

        return [
            'sorteio_ambas_marcam_minimo' => $regra,
            'sorteio_ambas_marcam_maximo' => $regra,
            'sorteio_ambas_nao_marcam_minimo' => $regra,
            'sorteio_ambas_nao_marcam_maximo' => $regra,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.between' => 'A cotação do sorteio deve ser de 1,01 a 50.',
            '*.numeric' => 'A cotação do sorteio deve ser um número.',
            '*.regex' => 'A cotação do sorteio deve ter até 2 casas decimais.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $configuracoes = Configuracoes::atual();

                foreach (self::PARES as $par => $mensagem) {
                    $minimo = (float) $this->input("{$par}_minimo", $configuracoes->{"{$par}_minimo"});
                    $maximo = (float) $this->input("{$par}_maximo", $configuracoes->{"{$par}_maximo"});

                    if ($minimo > $maximo) {
                        $validator->errors()->add("{$par}_minimo", $mensagem);
                    }
                }
            },
        ];
    }
}
