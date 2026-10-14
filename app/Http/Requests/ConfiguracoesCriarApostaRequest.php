<?php

namespace App\Http\Requests;

use App\Enums\LiquidacaoCriarAposta;
use App\Models\Configuracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Configurações gerais do Criar Aposta (spec 008, FR-013 e FR-014): valem para clientes e
 * visitantes; a liquidação vale para todo o sistema. Só os campos enviados são gravados.
 */
class ConfiguracoesCriarApostaRequest extends FormRequest
{
    private const VALOR = 'regex:/^\d+(\.\d{1,2})?$/';

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
            'criar_aposta_habilitado' => ['sometimes', 'boolean'],
            'desconto_criar_aposta' => ['sometimes', 'numeric', self::VALOR, 'between:0,100'],
            'quantidade_minima_criar_aposta' => ['sometimes', 'integer', 'between:2,20'],
            'quantidade_maxima_criar_aposta' => ['sometimes', 'integer', 'between:2,20'],
            'liquidacao_criar_aposta' => ['sometimes', Rule::enum(LiquidacaoCriarAposta::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'criar_aposta_habilitado.boolean' => 'O campo criar_aposta_habilitado deve ser verdadeiro ou falso.',
            'desconto_criar_aposta.numeric' => 'O desconto do Criar Aposta deve ser de 0 a 100%.',
            'desconto_criar_aposta.regex' => 'O desconto do Criar Aposta deve ter até 2 casas decimais.',
            'desconto_criar_aposta.between' => 'O desconto do Criar Aposta deve ser de 0 a 100%.',
            'quantidade_minima_criar_aposta.integer' => 'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
            'quantidade_minima_criar_aposta.between' => 'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
            'quantidade_maxima_criar_aposta.integer' => 'O máximo de palpites do Criar Aposta deve ser de 2 a 20 e não pode ser menor que o mínimo.',
            'quantidade_maxima_criar_aposta.between' => 'O máximo de palpites do Criar Aposta deve ser de 2 a 20 e não pode ser menor que o mínimo.',
            'liquidacao_criar_aposta.enum' => 'A liquidação do Criar Aposta deve ser Recalcular ou Anular bloco.',
        ];
    }

    /**
     * Mínimo ≤ máximo, comparando com o valor enviado ou, sem ele, com o atual.
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

                $atuais = Configuracoes::atual();
                $minimo = (int) $this->input('quantidade_minima_criar_aposta', $atuais->quantidade_minima_criar_aposta);
                $maximo = (int) $this->input('quantidade_maxima_criar_aposta', $atuais->quantidade_maxima_criar_aposta);

                if ($minimo > $maximo) {
                    $validator->errors()->add(
                        'quantidade_minima_criar_aposta',
                        'O mínimo de palpites do Criar Aposta deve ser de 2 a 20 e não pode passar do máximo.',
                    );
                }
            },
        ];
    }
}
