<?php

namespace App\Http\Requests;

use App\Enums\AlvoRegra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marcação de um campeonato ou confronto como não permitido para um alvo.
 */
class NaoPermitidosRequest extends FormRequest
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
        $de_campeonato = $this->is('api/campeonatos-nao-permitidos');

        return [
            'campeonatos_id' => [$de_campeonato ? 'required' : 'prohibited', 'integer', Rule::exists('campeonatos', 'id')->whereNull('deleted_at')],
            'confrontos_id' => [$de_campeonato ? 'prohibited' : 'required', 'integer', Rule::exists('confrontos', 'id')->whereNull('deleted_at')],
            'alvo' => ['required', Rule::enum(AlvoRegra::class)],
            'usuarios_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'clientes_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campeonatos_id.required' => 'O campeonato é obrigatório.',
            'campeonatos_id.prohibited' => 'Informe o confronto, não o campeonato.',
            'campeonatos_id.exists' => 'Campeonato não encontrado.',
            'confrontos_id.required' => 'O confronto é obrigatório.',
            'confrontos_id.prohibited' => 'Informe o campeonato, não o confronto.',
            'confrontos_id.exists' => 'Confronto não encontrado.',
            'alvo.required' => 'O alvo é obrigatório.',
            'alvo.enum' => 'O alvo deve ser Clientes, Vendedores ou Todos.',
            'usuarios_id.exists' => 'Usuário não encontrado.',
            'clientes_id.exists' => 'Cliente não encontrado.',
        ];
    }
}
