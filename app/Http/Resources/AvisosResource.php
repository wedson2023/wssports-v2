<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvisosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'imagem' => $this->url_imagem(),
            'link' => $this->link,
            'inicio_em' => $this->inicio_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'fim_em' => $this->fim_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'ativo' => $this->ativo,
            'quantidade_leituras' => $this->whenCounted('leituras'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
