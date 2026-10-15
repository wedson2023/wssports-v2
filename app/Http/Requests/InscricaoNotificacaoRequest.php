<?php

namespace App\Http\Requests;

use App\Services\InscricoesNotificacoes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscrição do aparelho nas notificações push (spec 010, contracts/api.md seção 3). As chaves só
 * são exigidas numa inscrição nova (conferido pelo serviço).
 */
class InscricaoNotificacaoRequest extends FormRequest
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
        $preferencias = array_fill_keys(
            array_map(fn (string $campo) => "preferencias.{$campo}", InscricoesNotificacoes::PREFERENCIAS),
            ['sometimes', 'boolean'],
        );

        return [
            'endpoint' => ['required', 'string', 'url', 'starts_with:https://', 'max:2000'],
            'chaves' => ['sometimes', 'array'],
            'chaves.p256dh' => ['sometimes', 'string', 'max:255'],
            'chaves.auth' => ['sometimes', 'string', 'max:255'],
            'codificacao' => ['nullable', 'in:aes128gcm,aesgcm'],
            'preferencias' => ['sometimes', 'array'],
            ...$preferencias,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.required' => 'O endereço de entrega das notificações é obrigatório.',
            'endpoint.url' => 'O endereço de entrega das notificações é inválido.',
            'endpoint.starts_with' => 'O endereço de entrega das notificações deve usar https.',
            'endpoint.max' => 'O endereço de entrega das notificações é longo demais.',
            'chaves.*.max' => 'A chave do aparelho é inválida.',
            'codificacao.in' => 'A codificação deve ser aes128gcm ou aesgcm.',
            'preferencias.*.boolean' => 'Cada tipo de aviso deve ser verdadeiro ou falso.',
        ];
    }
}
