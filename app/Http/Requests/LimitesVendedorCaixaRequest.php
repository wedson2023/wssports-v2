<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ajuste dos limites do vendedor pelo caixa (spec 017, FR-028, contracts/api.md §1.4): valor a somar
 * (positivo) ou a subtrair (negativo) em cada limite; ao menos um diferente de zero.
 */
class LimitesVendedorCaixaRequest extends FormRequest
{
    /**
     * Campo do pedido → coluna do limite.
     */
    public const LIMITES = [
        'geral' => 'limite_geral',
        'duplo' => 'limite_duplo',
        'simples' => 'limite_simples',
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
        return array_fill_keys(array_keys(self::LIMITES), ['nullable', 'numeric', 'decimal:0,2', 'between:-9999999999999.99,9999999999999.99']);
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

            if ($this->ajustes() === []) {
                $validador->errors()->add('geral', 'Informe ao menos um valor para ajustar.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.numeric' => 'Informe um valor válido.',
            '*.decimal' => 'O valor deve ter no máximo duas casas.',
            '*.between' => 'O valor informado é muito alto.',
        ];
    }

    /**
     * Ajustes diferentes de zero: coluna do limite → valor com duas casas.
     *
     * @return array<string, string>
     */
    public function ajustes(): array
    {
        $ajustes = [];

        foreach (self::LIMITES as $campo => $coluna) {
            $valor = bcadd((string) ($this->input($campo) ?? '0'), '0', 2);

            if (bccomp($valor, '0', 2) !== 0) {
                $ajustes[$coluna] = $valor;
            }
        }

        return $ajustes;
    }
}
