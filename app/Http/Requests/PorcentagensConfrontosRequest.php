<?php

namespace App\Http\Requests;

use App\Enums\AlvoRegra;
use App\Support\CodigosCotacao;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cotação desejada para códigos de um confronto; o sistema guarda a diferença para a cotação do
 * provedor (valor fixo).
 */
class PorcentagensConfrontosRequest extends FormRequest
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
        return [
            'alvo' => ['required', Rule::enum(AlvoRegra::class)],
            'usuarios_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'cotacoes' => ['required', 'array', 'min:1', $this->cotacoes_validas()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alvo.required' => 'O alvo é obrigatório.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
            'usuarios_id.exists' => 'Usuário não encontrado.',
            'cotacoes.required' => 'Informe ao menos uma cotação.',
            'cotacoes.array' => 'Cotações deve ser um objeto com os códigos de cotação.',
            'cotacoes.min' => 'Informe ao menos uma cotação.',
        ];
    }

    private function cotacoes_validas(): Closure
    {
        return function (string $atributo, mixed $cotacoes, Closure $falhar) {
            if (! is_array($cotacoes) || array_is_list($cotacoes)) {
                $falhar('Cotações deve ser um objeto com os códigos de cotação.');

                return;
            }

            foreach ($cotacoes as $codigo => $valor) {
                if (! CodigosCotacao::e_cotacao((string) $codigo)) {
                    $falhar("O código {$codigo} não existe.");

                    return;
                }

                if (! is_numeric($valor) || (float) $valor <= 0 || ! preg_match('/^\d+(\.\d{1,2})?$/', (string) $valor)) {
                    $falhar("A cotação de {$codigo} deve ser maior que zero, com até 2 casas decimais.");

                    return;
                }
            }
        };
    }
}
