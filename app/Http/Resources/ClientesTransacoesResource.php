<?php

namespace App\Http\Resources;

use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Linha do extrato. O autor só aparece para o painel.
 */
class ClientesTransacoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carteira' => $this->carteira,
            'tipo' => $this->tipo,
            'origem' => $this->origem,
            'referencia_id' => $this->referencia_id,
            'valor' => $this->valor,
            'saldo_anterior' => $this->saldo_anterior,
            'saldo_posterior' => $this->saldo_posterior,
            'observacao' => $this->observacao,
            'autor' => $this->when(
                $request->user() instanceof Usuarios,
                fn () => $this->autor ? ['id' => $this->autor->id, 'nome' => $this->autor->nome] : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
