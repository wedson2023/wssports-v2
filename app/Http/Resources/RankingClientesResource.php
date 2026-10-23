<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Linha do ranking de clientes (spec 019, contracts/api.md §4), já montada pelo RankingClientes:
 * posição, dados do cliente e o bloco do critério (os outros blocos vêm nulos).
 */
class RankingClientesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
