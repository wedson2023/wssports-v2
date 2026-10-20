<?php

namespace App\Http\Requests;

use App\Models\Configuracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Configuração do aviso de combinação de risco nova no WhatsApp do Admin (spec 014, FR-048,
 * contracts/api.md §3). Só os campos enviados são gravados; a chave ausente mantém a atual.
 */
class ConfiguracoesAvisosWhatsappRequest extends FormRequest
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
            'aviso_combinacao_whatsapp' => ['sometimes', 'boolean'],
            'telefone_whatsapp_admin' => ['sometimes', 'nullable', 'regex:/^\d{12,15}$/'],
            'chave_callmebot' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Ligar o aviso exige telefone e chave, contando o enviado e o já gravado.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty() || ! $this->boolean('aviso_combinacao_whatsapp')) {
                return;
            }

            $atual = Configuracoes::atual();
            $telefone = $this->has('telefone_whatsapp_admin') ? $this->input('telefone_whatsapp_admin') : $atual->telefone_whatsapp_admin;
            $chave = $this->has('chave_callmebot') ? $this->input('chave_callmebot') : $atual->chave_callmebot;

            if (blank($telefone) || blank($chave)) {
                $validador->errors()->add('aviso_combinacao_whatsapp', 'Informe o telefone e a chave do CallMeBot para ligar o aviso.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aviso_combinacao_whatsapp.boolean' => 'O aviso deve ser ligado ou desligado.',
            'telefone_whatsapp_admin.regex' => 'O telefone deve ter só números, com DDI e DDD (12 a 15 dígitos).',
            'chave_callmebot.string' => 'A chave do CallMeBot é inválida.',
            'chave_callmebot.max' => 'A chave do CallMeBot deve ter no máximo 64 caracteres.',
        ];
    }
}
