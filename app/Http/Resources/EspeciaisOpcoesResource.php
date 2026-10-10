<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspeciaisOpcoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cotacao' => $this->cotacao,
            'ativo' => $this->ativo,
        ];
    }
}
