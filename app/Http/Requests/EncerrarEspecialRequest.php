<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Encerramento de uma categoria especial: a opção vencedora precisa ser da própria categoria.
 */
class EncerrarEspecialRequest extends FormRequest
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
            'especiais_opcoes_id' => ['required', 'integer', $this->da_categoria()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'especiais_opcoes_id.required' => 'Informe a opção vencedora.',
            'especiais_opcoes_id.integer' => 'A opção vencedora é inválida.',
        ];
    }

    private function da_categoria(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar) {
            if (! $this->route('especial')->opcoes()->whereKey((int) $valor)->exists()) {
                $falhar('A opção vencedora não pertence a esta categoria.');
            }
        };
    }
}
