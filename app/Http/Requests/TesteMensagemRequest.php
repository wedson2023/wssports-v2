<?php

namespace App\Http\Requests;

use App\Enums\CanalMensagem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Canal do botão de teste (spec 025, FR-020): o destinatário é sempre o Admin configurado, por isso o
 * pedido não tem esse campo.
 */
class TesteMensagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'canal' => ['required', Rule::in([CanalMensagem::WhatsApp->value, CanalMensagem::Email->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'canal.required' => 'Escolha o canal do teste.',
            'canal.in' => 'Escolha WhatsApp ou E-mail.',
        ];
    }
}
