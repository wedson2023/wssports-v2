<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfiguracoesAposta;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações de aposta e saque do cliente, com limites coerentes.
 */
class ClientesConfiguracoesRequest extends FormRequest
{
    use ConfiguracoesAposta;

    private const VALOR = 'regex:/^\d{1,13}(\.\d{1,2})?$/';

    /**
     * Regras de aposta do cliente acrescentadas pela spec 004 (opcionais no envio).
     */
    private const CAMPOS_APOSTA = ['apostar_jogadores', 'periodo_jogos', 'delay_ao_vivo', 'multiplicador', 'ganho_multiplo_palpites'];

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
            'realizar_aposta' => ['required', 'boolean'],
            'apostar_ao_vivo' => ['required', 'boolean'],
            'apostar_outros_esportes' => ['required', 'boolean'],
            'cancelar_aposta' => ['required', 'boolean'],
            'aceita_promocao' => ['required', 'boolean'],
            'bloquear_saque' => ['required', 'boolean'],
            'quantidade_minima_opcoes' => ['required', 'integer', 'min:1', 'lte:quantidade_maxima_opcoes'],
            'quantidade_maxima_opcoes' => ['required', 'integer', 'min:1'],
            'valor_minimo_aposta' => ['required', 'numeric', self::VALOR, 'gt:0', 'lte:valor_maximo_aposta'],
            'valor_maximo_aposta' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'premio_maximo' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'valor_maximo_diario' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'valor_maximo_saque_diario' => ['required', 'numeric', self::VALOR, 'gt:0'],
            'quantidade_maxima_saques_diaria' => ['required', 'integer', 'min:1'],
            'odd_minima' => ['required', 'numeric', 'min:1', 'lte:odd_maxima'],
            'odd_maxima' => ['required', 'numeric', 'min:1'],
            'esportes_permitidos' => ['required', 'array', 'min:1'],
            'esportes_permitidos.*' => ['required', 'string', 'max:50', 'distinct'],
            ...$this->regras_aposta(self::CAMPOS_APOSTA),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'O campo :attribute é obrigatório.',
            '*.boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.regex' => 'O campo :attribute deve ter até 2 casas decimais.',
            '*.gt' => 'O campo :attribute deve ser maior que zero.',
            'quantidade_minima_opcoes.min' => 'A quantidade mínima de opções deve ser pelo menos 1.',
            'quantidade_minima_opcoes.lte' => 'A quantidade mínima de opções não pode ser maior que a máxima.',
            'quantidade_maxima_opcoes.min' => 'A quantidade máxima de opções deve ser pelo menos 1.',
            'valor_minimo_aposta.lte' => 'O valor mínimo por aposta não pode ser maior que o máximo.',
            'quantidade_maxima_saques_diaria.min' => 'A quantidade máxima de saques por dia deve ser pelo menos 1.',
            'odd_minima.min' => 'A odd mínima deve ser pelo menos 1,00.',
            'odd_minima.lte' => 'A odd mínima não pode ser maior que a odd máxima.',
            'odd_maxima.min' => 'A odd máxima deve ser pelo menos 1,00.',
            'esportes_permitidos.array' => 'Os esportes permitidos devem ser uma lista.',
            'esportes_permitidos.min' => 'Informe pelo menos um esporte permitido.',
            'esportes_permitidos.*.max' => 'Cada esporte deve ter no máximo 50 caracteres.',
            'esportes_permitidos.*.distinct' => 'Os esportes permitidos não podem se repetir.',
            ...$this->mensagens_aposta(self::CAMPOS_APOSTA),
        ];
    }
}
