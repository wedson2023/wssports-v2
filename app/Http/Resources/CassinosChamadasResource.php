<?php

namespace App\Http\Resources;

use App\Models\CassinosChamadas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chamada recebida do provedor do cassino, sem a chave (spec 011, FR-026).
 *
 * @mixin CassinosChamadas
 */
class CassinosChamadasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comando' => $this->comando,
            'login' => $this->login,
            'trade_id' => $this->trade_id,
            'dados' => $this->dados,
            'resposta' => $this->resposta,
            'resultado' => $this->resultado,
            'erro' => $this->erro,
            'ip' => $this->ip,
            'duracao_ms' => $this->duracao_ms,
            'data' => $this->created_at->copy()->setTimezone('-03:00')->toIso8601String(),
        ];
    }
}
