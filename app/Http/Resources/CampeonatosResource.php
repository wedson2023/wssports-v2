<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampeonatosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_externo' => $this->codigo_externo,
            'nome' => $this->nome,
            'pais' => $this->pais,
            'bandeira' => $this->bandeira,
            'ativo' => $this->ativo,
            'favorito' => $this->favorito,
            'manual' => $this->manual,
            // se há um não permitido que vale para quem consulta
            'nao_permitido' => (bool) ($this->nao_permitido ?? false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
