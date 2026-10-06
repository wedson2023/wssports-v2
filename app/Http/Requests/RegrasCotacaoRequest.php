<?php

namespace App\Http\Requests;

use App\Enums\AlvoRegra;
use App\Support\CodigosCotacao;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alteração de porcentagens ou do teto: "valores" (só os códigos alterados) ou "todos" (o mesmo
 * valor para os 324 códigos). Porcentagens de −100 a 100; tetos a partir de 1,00; 2 casas.
 */
class RegrasCotacaoRequest extends FormRequest
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
        $exige_tipo = $this->is('api/porcentagens-vendedores/*') || $this->is('api/porcentagens-clientes');
        $com_alvo = $this->is('api/porcentagens-campeonatos/*');

        return [
            'tipo' => [$exige_tipo ? 'required' : 'prohibited', 'in:pre_jogo,ao_vivo'],
            'valores' => ['required_without:todos', 'prohibits:todos', 'array', $this->valores_validos()],
            'todos' => ['required_without:valores', 'numeric', 'regex:/^-?\d+(\.\d{1,2})?$/', ...$this->intervalo()],
            'alvo' => [$com_alvo ? 'required' : 'prohibited', Rule::enum(AlvoRegra::class)],
            // dono só nas regras com alvo; cliente só nas porcentagens de clientes
            'usuarios_id' => [$com_alvo ? 'nullable' : 'prohibited', 'integer', 'exists:usuarios,id'],
            'clientes_id' => [$this->is('api/porcentagens-clientes') ? 'nullable' : 'prohibited', 'integer', 'exists:clientes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'O tipo (pre_jogo ou ao_vivo) é obrigatório.',
            'tipo.prohibited' => 'O tipo não se aplica a esta regra.',
            'tipo.in' => 'O tipo deve ser pre_jogo ou ao_vivo.',
            'valores.required_without' => 'Informe valores ou todos.',
            'valores.prohibits' => 'Informe valores ou todos, não os dois.',
            'valores.array' => 'Valores deve ser um objeto com os códigos de cotação.',
            'todos.required_without' => 'Informe valores ou todos.',
            'todos.numeric' => 'O campo todos deve ser um número.',
            'todos.regex' => 'O campo todos deve ter até 2 casas decimais.',
            'todos.between' => 'A porcentagem deve estar entre -100 e 100.',
            'todos.min' => 'O teto deve ser maior ou igual a 1,00.',
            'alvo.required' => 'O alvo é obrigatório.',
            'alvo.prohibited' => 'O alvo não se aplica a esta regra.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
            'usuarios_id.prohibited' => 'O usuário não se aplica a esta regra.',
            'usuarios_id.exists' => 'Usuário não encontrado.',
            'clientes_id.prohibited' => 'O cliente não se aplica a esta regra.',
            'clientes_id.exists' => 'Cliente não encontrado.',
        ];
    }

    private function e_teto(): bool
    {
        return $this->is('api/confrontos-teto-cotacoes');
    }

    /**
     * @return list<string>
     */
    private function intervalo(): array
    {
        return $this->e_teto() ? ['min:1'] : ['between:-100,100'];
    }

    /**
     * Códigos de odd1 a odd323 e jogador, valores numéricos com até 2 casas no intervalo da regra.
     */
    private function valores_validos(): Closure
    {
        $teto = $this->e_teto();

        return function (string $atributo, mixed $valores, Closure $falhar) use ($teto) {
            if (! is_array($valores) || ($valores !== [] && array_is_list($valores))) {
                $falhar('Valores deve ser um objeto com os códigos de cotação.');

                return;
            }

            foreach ($valores as $codigo => $valor) {
                if (! CodigosCotacao::e_regra((string) $codigo)) {
                    $falhar("O código {$codigo} não existe.");

                    return;
                }

                if (! is_numeric($valor) || ! preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $valor)) {
                    $falhar("O valor de {$codigo} deve ser um número com até 2 casas decimais.");

                    return;
                }

                $valor = (float) $valor;

                if ($teto && $valor != 0 && $valor < 1) {
                    $falhar("O teto de {$codigo} deve ser maior ou igual a 1,00 (0 remove o teto).");

                    return;
                }

                if (! $teto && ($valor < -100 || $valor > 100)) {
                    $falhar("A porcentagem de {$codigo} deve estar entre -100 e 100.");

                    return;
                }
            }
        };
    }
}
