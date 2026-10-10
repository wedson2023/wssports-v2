<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspeciaisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'data_limite' => $this->data_limite->setTimezone('-03:00')->toIso8601String(),
            'situacao' => $this->situacao,
            'ativo' => $this->ativo,
            'opcoes' => EspeciaisOpcoesResource::collection($this->whenLoaded('opcoes')),
            'opcao_vencedora' => $this->when(
                $this->especiais_opcoes_id_vencedora !== null,
                fn () => new EspeciaisOpcoesResource($this->opcao_vencedora),
            ),
            'encerrado_em' => $this->encerrado_em?->setTimezone('-03:00')->toIso8601String(),
            'quantidade_palpites' => $this->whenCounted('palpites'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
