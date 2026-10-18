<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosAfiliado;
use App\Rules\CpfValido;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Restauração do afiliado da lixeira (contracts/api §2.15): valores novos opcionais para os dados
 * únicos que já estiverem em uso por outro.
 */
class RestaurarAfiliadosRequest extends FormRequest
{
    use DadosAfiliado;

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_afiliado();

        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => mb_strtoupper(trim($this->input('codigo')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9._-]{3,50}$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'cpf' => ['sometimes', 'nullable', 'string', new CpfValido],
            'codigo' => ['sometimes', 'required', 'string', 'regex:/^[A-Z0-9._-]{4,50}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensagens_dados_afiliado(),
            'codigo.required' => 'O código é obrigatório.',
            'codigo.regex' => 'O código deve ter de 4 a 50 caracteres, só com letras, números, ponto, hífen ou sublinhado.',
        ];
    }
}
