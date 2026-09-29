<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DadosCliente;
use App\Rules\CpfValido;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Restauração de cliente excluído. Os dados únicos novos só são necessários quando os
 * originais já estão em uso por outro cliente (a checagem de conflito fica no controller).
 */
class RestaurarClientesRequest extends FormRequest
{
    use DadosCliente;

    public function authorize(): bool
    {
        // a permissão é checada pelo middleware do controller
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizar_dados_cliente();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ddi = (string) ($this->input('ddi') ?? $this->route('cliente')->ddi);

        return [
            'ddi' => ['sometimes', 'required', 'digits_between:1,3'],
            'telefone' => ['sometimes', 'required', 'string', $ddi === '55' ? 'digits_between:10,11' : 'digits_between:4,14'],
            'cpf' => ['sometimes', 'nullable', 'string', new CpfValido],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensagens_dados_cliente();
    }
}
