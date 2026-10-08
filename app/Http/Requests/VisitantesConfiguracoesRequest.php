<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfiguracoesAposta;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurações de quem não está logado.
 */
class VisitantesConfiguracoesRequest extends FormRequest
{
    use ConfiguracoesAposta;

    /**
     * Regras da aposta do visitante acrescentadas pela spec 004 (opcionais no envio).
     */
    private const CAMPOS_APOSTA = [
        'apostar_jogadores',
        'periodo_jogos',
        'data_travamento_sistema',
        'quantidade_minima_opcoes',
        'quantidade_maxima_opcoes',
        'valor_minimo_aposta',
        'valor_maximo_aposta',
        'odd_minima',
        'premio_maximo',
        'multiplicador',
        'ganho_multiplo_palpites',
        'horas_validade_codigo',
    ];

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->converter_data_travamento();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'esportes_permitidos' => ['required', 'array', 'min:1'],
            'esportes_permitidos.*' => ['required', 'string', 'max:50', 'distinct'],
            'apostar_outros_esportes' => ['required', 'boolean'],
            'ao_vivo_habilitado' => ['required', 'boolean'],
            ...$this->regras_aposta(self::CAMPOS_APOSTA),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'esportes_permitidos.required' => 'Informe ao menos um esporte permitido.',
            'esportes_permitidos.array' => 'Os esportes permitidos devem ser uma lista.',
            'esportes_permitidos.min' => 'Informe ao menos um esporte permitido.',
            'esportes_permitidos.*.required' => 'O nome do esporte é obrigatório.',
            'esportes_permitidos.*.max' => 'O nome do esporte deve ter no máximo 50 caracteres.',
            'esportes_permitidos.*.distinct' => 'Os esportes permitidos não podem se repetir.',
            'apostar_outros_esportes.required' => 'O campo apostar outros esportes é obrigatório.',
            'apostar_outros_esportes.boolean' => 'O campo apostar outros esportes deve ser verdadeiro ou falso.',
            'ao_vivo_habilitado.required' => 'O campo ao vivo habilitado é obrigatório.',
            'ao_vivo_habilitado.boolean' => 'O campo ao vivo habilitado deve ser verdadeiro ou falso.',
            ...$this->mensagens_aposta(self::CAMPOS_APOSTA),
        ];
    }
}
