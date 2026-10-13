<?php

namespace App\Http\Resources;

use App\Models\Saques;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saque na fila do painel (spec 007, contracts/api.md §10).
 *
 * @mixin Saques
 */
class SaquesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'nome' => $this->cliente->nome,
                'telefone' => $this->cliente->telefone,
            ]),
            'valor' => $this->valor,
            'pix' => [
                'tipo_chave' => $this->pix_tipo_chave,
                'chave' => $this->pix_chave,
                'nome_titular' => $this->pix_nome_titular,
            ],
            'modo' => $this->modo,
            'situacao' => $this->situacao,
            'motivo_recusa' => $this->motivo_recusa,
            'motivo_falha' => $this->motivo_falha,
            'decidido_por' => $this->whenLoaded('decidido_por_usuario', fn () => $this->decidido_por_usuario ? ['id' => $this->decidido_por_usuario->id, 'nome' => $this->decidido_por_usuario->nome] : null),
            'decidido_em' => $this->decidido_em,
            'pago_em' => $this->pago_em,
            'created_at' => $this->created_at,
        ];
    }
}
