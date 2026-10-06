<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registro de não permitido (campeonato, confronto do pré-jogo ou do ao vivo).
 */
class NaoPermitidosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campeonatos_id' => $this->when(isset($this->campeonatos_id), $this->campeonatos_id),
            'confrontos_id' => $this->when(isset($this->confrontos_id), $this->confrontos_id),
            'alvo' => $this->alvo,
            'usuarios_id' => $this->usuarios_id,
            'clientes_id' => $this->clientes_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
