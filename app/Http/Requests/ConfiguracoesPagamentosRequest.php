<?php

namespace App\Http\Requests;

use App\Enums\MeioDeposito;
use App\Enums\ModoSaque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Configurações de pagamento editadas pelo painel (spec 007, FR-052). O percentual de comissão
 * não é aceito aqui: só muda direto no banco (FR-028).
 */
class ConfiguracoesPagamentosRequest extends FormRequest
{
    private const VALOR = 'regex:/^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'depositos_habilitados' => ['sometimes', 'boolean'],
            'saques_habilitados' => ['sometimes', 'boolean'],
            'deposito_minimo' => ['sometimes', 'numeric', self::VALOR, 'min:1', ...($this->has('deposito_maximo') ? ['lte:deposito_maximo'] : [])],
            'deposito_maximo' => ['sometimes', 'numeric', self::VALOR, 'min:1'],
            'saque_minimo' => ['sometimes', 'numeric', self::VALOR, 'min:0.01'],
            'rollover_deposito' => ['sometimes', 'boolean'],
            'rollover_deposito_vezes' => ['sometimes', 'integer', 'between:1,20'],
            'cashback_percentual' => ['sometimes', 'numeric', self::VALOR, 'between:0,100'],
            'modo_saque' => ['sometimes', Rule::enum(ModoSaque::class)],
            'saque_automatico_maximo' => ['sometimes', 'numeric', self::VALOR, 'min:0.01'],
            'meios_deposito' => ['sometimes', 'array', 'min:1'],
            'meios_deposito.*' => ['distinct', Rule::enum(MeioDeposito::class)],
            'parcelas_maximas' => ['sometimes', 'integer', 'between:1,12'],
            'minutos_expiracao_pix' => ['sometimes', 'integer', 'between:5,1440'],
            'minutos_acesso_assistido' => ['sometimes', 'integer', 'between:5,480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.regex' => 'O campo :attribute deve ter até 2 casas decimais.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            'deposito_minimo.min' => 'O depósito mínimo deve ser de pelo menos R$ 1,00.',
            'deposito_minimo.lte' => 'O depósito mínimo não pode ser maior que o máximo.',
            'deposito_maximo.min' => 'O depósito máximo deve ser de pelo menos R$ 1,00.',
            'saque_minimo.min' => 'O saque mínimo deve ser maior que zero.',
            'saque_automatico_maximo.min' => 'O valor máximo do saque automático deve ser maior que zero.',
            'rollover_deposito_vezes.between' => 'O rollover do depósito deve ser de 1 a 20 vezes.',
            'cashback_percentual.between' => 'O cashback deve ser de 0 a 100%.',
            'modo_saque.enum' => 'O modo de saque deve ser Manual ou Automático.',
            'meios_deposito.array' => 'Os meios de depósito devem ser uma lista.',
            'meios_deposito.min' => 'Habilite pelo menos um meio de depósito.',
            'meios_deposito.*.enum' => 'Os meios de depósito aceitos são Pix e Cartão de crédito.',
            'meios_deposito.*.distinct' => 'Meio de depósito repetido.',
            'parcelas_maximas.between' => 'O máximo de parcelas deve ser de 1 a 12.',
            'minutos_expiracao_pix.between' => 'O prazo do Pix deve ser de 5 a 1440 minutos.',
            'minutos_acesso_assistido.between' => 'O tempo do acesso assistido deve ser de 5 a 480 minutos.',
        ];
    }
}
