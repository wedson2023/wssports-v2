<?php

namespace App\Http\Requests;

use App\Enums\CanalMensagem;
use App\Enums\TipoMensagem;
use App\Models\Configuracoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Canais das mensagens, destinos do Admin e confirmação de cadastro (spec 025, FR-022).
 */
class ConfiguracoesMensagensRequest extends FormRequest
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
            'aviso_combinacao_whatsapp' => ['sometimes', 'boolean'],
            'telefone_whatsapp_admin' => ['sometimes', 'nullable', 'regex:/^\d{12,15}$/'],
            'email_admin' => ['sometimes', 'nullable', 'email', 'max:150'],
            'confirmacao_cadastro' => ['sometimes', 'boolean'],
            'mensagens' => ['sometimes', 'array'],
            'mensagens.*.tipo' => ['required', 'string', Rule::in(array_map(fn (TipoMensagem $tipo) => $tipo->name, TipoMensagem::escolhiveis()))],
            'mensagens.*.canal' => ['required', Rule::enum(CanalMensagem::class)],
        ];
    }

    /**
     * O aviso de combinação ligado precisa de algum destino.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($validador->errors()->isNotEmpty()) {
                    return;
                }

                $atual = Configuracoes::atual();
                $ligado = $this->has('aviso_combinacao_whatsapp') ? $this->boolean('aviso_combinacao_whatsapp') : $atual->aviso_combinacao_whatsapp;
                $telefone = $this->has('telefone_whatsapp_admin') ? $this->input('telefone_whatsapp_admin') : $atual->telefone_whatsapp_admin;
                $email = $this->has('email_admin') ? $this->input('email_admin') : $atual->email_admin;

                if ($ligado && blank($telefone) && blank($email)) {
                    $validador->errors()->add('aviso_combinacao_whatsapp', 'Informe o telefone ou o e-mail do Admin para ligar o aviso.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telefone_whatsapp_admin.regex' => 'O telefone deve ter só números, com DDI e DDD (12 a 15 dígitos).',
            'email_admin.email' => 'Informe um e-mail válido.',
            'email_admin.max' => 'O e-mail deve ter no máximo 150 caracteres.',
            'mensagens.*.tipo.in' => 'Mensagem inválida.',
            'mensagens.*.canal' => 'Escolha WhatsApp, E-mail ou Os dois.',
        ];
    }
}
