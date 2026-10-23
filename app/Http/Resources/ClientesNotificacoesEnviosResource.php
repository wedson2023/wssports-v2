<?php

namespace App\Http\Resources;

use App\Models\ClientesNotificacoesEnvios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envio manual de notificação push (spec 019, contracts/api.md §6.4).
 *
 * @mixin ClientesNotificacoesEnvios
 */
class ClientesNotificacoesEnviosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'publico' => $this->publico,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'alcancados' => $this->alcancados,
            'entregues' => $this->entregues,
            'falhas' => $this->falhas,
            'concluido_em' => $this->concluido_em,
            'created_at' => $this->created_at,
        ];
    }
}
