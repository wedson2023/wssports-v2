<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuração do aviso de combinação de risco no WhatsApp do Admin (spec 014, contracts/api.md §3).
 * A chave do CallMeBot nunca sai inteira: só se está definida e os 4 últimos caracteres.
 *
 * @mixin Configuracoes
 */
class ConfiguracoesAvisosWhatsappResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $chave = $this->chave_callmebot;

        return [
            'aviso_combinacao_whatsapp' => $this->aviso_combinacao_whatsapp,
            'telefone_whatsapp_admin' => $this->telefone_whatsapp_admin,
            'chave_callmebot_definida' => filled($chave),
            'chave_callmebot_final' => filled($chave) ? substr($chave, -4) : null,
        ];
    }
}
