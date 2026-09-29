<?php

namespace App\Http\Resources;

use App\Models\ClientesConfiguracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações de aposta e saque (do cliente ou as padrão).
 */
class ClientesConfiguracoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only(ClientesConfiguracoes::CAMPOS),
            'updated_at' => $this->updated_at,
        ];
    }
}
