<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regra de comissão do afiliado no histórico (contracts/api §2.9).
 */
class AfiliadosComissoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_ganho' => $this->tipo_ganho,
            'percentual' => $this->percentual,
            'vigente_desde' => $this->vigente_desde,
            'vigente_ate' => $this->vigente_ate,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario === null ? null : [
                'id' => $this->usuario->id,
                'nome' => $this->usuario->nome,
            ]),
        ];
    }
}
