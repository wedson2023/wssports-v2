<?php

namespace App\Http\Requests;

use App\Models\PorcentagensCadeados;
use App\Support\CodigosCotacao;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Trancar ou destrancar cotações de um usuário da rede (spec 016, FR-019): na camada vendedores
 * por tipo (pré-jogo ou ao vivo); na camada campeonatos, num campeonato (vale para os dois tipos).
 */
class CadeadosCotacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a permissão e o alcance são checados no controller e no serviço de cadeados
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendedores = $this->input('camada') === PorcentagensCadeados::VENDEDORES;

        return [
            'camada' => ['required', 'in:'.PorcentagensCadeados::VENDEDORES.','.PorcentagensCadeados::CAMPEONATOS],
            'usuarios_id' => ['required', 'integer', 'exists:usuarios,id'],
            'tipo' => [$vendedores ? 'required' : 'prohibited', 'in:pre_jogo,ao_vivo'],
            'campeonatos_id' => [$vendedores ? 'prohibited' : 'required', 'integer', 'exists:campeonatos,id'],
            'codigos' => ['required', 'array', 'min:1', $this->codigos_validos()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'camada.required' => 'A camada é obrigatória.',
            'camada.in' => 'A camada deve ser vendedores ou campeonatos.',
            'usuarios_id.required' => 'Informe o usuário.',
            'usuarios_id.exists' => 'Usuário não encontrado.',
            'tipo.required' => 'O tipo (pre_jogo ou ao_vivo) é obrigatório.',
            'tipo.prohibited' => 'O tipo não se aplica ao cadeado de campeonato.',
            'tipo.in' => 'O tipo deve ser pre_jogo ou ao_vivo.',
            'campeonatos_id.required' => 'Informe o campeonato.',
            'campeonatos_id.prohibited' => 'O campeonato só se aplica à camada campeonatos.',
            'campeonatos_id.exists' => 'Campeonato não encontrado.',
            'codigos.required' => 'Informe ao menos uma cotação.',
            'codigos.array' => 'As cotações devem ser uma lista.',
            'codigos.min' => 'Informe ao menos uma cotação.',
        ];
    }

    /**
     * Códigos de odd1 a odd323 e jogador.
     */
    private function codigos_validos(): Closure
    {
        return function (string $atributo, mixed $codigos, Closure $falhar) {
            foreach ((array) $codigos as $codigo) {
                if (! is_string($codigo) || ! CodigosCotacao::e_regra($codigo)) {
                    $falhar('Código de cotação inválido: '.(is_scalar($codigo) ? $codigo : '?').'.');

                    return;
                }
            }
        };
    }
}
