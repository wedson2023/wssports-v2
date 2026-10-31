<?php

namespace App\Http\Resources;

use App\Enums\CanalMensagem;
use App\Enums\TipoMensagem;
use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Seção "Mensagens" da tela Configurações (spec 025, contracts/api.md).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesMensagensResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'aviso_combinacao_whatsapp' => $this->aviso_combinacao_whatsapp,
            'telefone_whatsapp_admin' => $this->telefone_whatsapp_admin,
            'email_admin' => $this->email_admin,
            'confirmacao_cadastro' => $this->confirmacao_cadastro,
            'mensagens' => array_map(fn (TipoMensagem $tipo) => [
                'tipo' => $tipo->name,
                'nome' => $tipo->value,
                'destinatario' => $tipo->destinatario(),
                'canal' => $this->canal_mensagem($tipo)->value,
                'reserva' => $tipo->tem_reserva(),
            ], TipoMensagem::escolhiveis()),
            'canais' => array_map(fn (CanalMensagem $canal) => $canal->value, CanalMensagem::cases()),
        ];
    }
}
