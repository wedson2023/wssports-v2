<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VencedorasEspecial;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Encerramento de uma categoria especial com uma ou mais vencedoras (spec 024): a quantidade de
 * vencedoras da categoria e, com empate na última vaga, a divisão por empate.
 */
class EncerrarEspecialRequest extends FormRequest
{
    use VencedorasEspecial;

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
        return $this->regras_vencedoras();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensagens_vencedoras();
    }
}
