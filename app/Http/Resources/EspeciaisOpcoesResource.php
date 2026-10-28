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
            // situação, resultado e anulação da opção (spec 024)
            'situacao' => $this->situacao_exibida(),
            'vencedora' => $this->vencedora,
            'empatada' => $this->empatada,
            'anulada_em' => $this->anulada_em?->setTimezone('-03:00')->toIso8601String(),
        ];
    }
}
