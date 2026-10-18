<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prestação de contas do afiliado (contracts/api §1.9 e §2.12). Quem prestou só aparece na gestão.
 */
class AfiliadosPrestacoesContasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'valor_apurado' => $this->valor_apurado,
            'valor_pago' => $this->valor_pago,
            'quantidade_lancamentos' => $this->quantidade_lancamentos,
            'periodo_inicio' => $this->periodo_inicio,
            'periodo_fim' => $this->periodo_fim,
            'observacao' => $this->observacao,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario === null ? null : [
                'id' => $this->usuario->id,
                'nome' => $this->usuario->nome,
            ]),
        ];
    }
}
