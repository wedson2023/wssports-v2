<?php

namespace App\Http\Requests;

use App\Enums\PublicoNotificacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Público e texto de uma notificação push manual (spec 019, FR-031, data-model §7). Na contagem dos
 * alcançados, título e mensagem não são exigidos.
 */
class NotificacaoManualRequest extends FormRequest
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
        $com_texto = $this->route()?->getActionMethod() === 'store';

        return [
            'publico' => ['required', Rule::enum(PublicoNotificacao::class)],
            'clientes_ids' => ['required_if:publico,'.PublicoNotificacao::PorId->value, 'nullable', 'array', 'min:1', 'max:500'],
            'clientes_ids.*' => ['integer', 'distinct'],
            'dias' => ['required_if:publico,'.PublicoNotificacao::SemInteragir->value, 'nullable', 'integer', 'between:1,365'],
            'titulo' => [$com_texto ? 'required' : 'nullable', 'string', 'max:50'],
            'mensagem' => [$com_texto ? 'required' : 'nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'publico.required' => 'Escolha o público.',
            'publico.enum' => 'O público deve ser Todos, Por ID ou Sem interagir.',
            'clientes_ids.required_if' => 'Escolha ao menos um cliente.',
            'clientes_ids.min' => 'Escolha ao menos um cliente.',
            'clientes_ids.max' => 'Escolha no máximo 500 clientes.',
            'clientes_ids.*' => 'Cliente inválido.',
            'dias.required_if' => 'Informe há quantos dias o cliente está sem interagir.',
            'dias.*' => 'Os dias devem ser um número de 1 a 365.',
            'titulo.required' => 'Informe o título.',
            'titulo.max' => 'O título deve ter no máximo 50 caracteres.',
            'mensagem.required' => 'Informe a mensagem.',
            'mensagem.max' => 'A mensagem deve ter no máximo 150 caracteres.',
        ];
    }
}
