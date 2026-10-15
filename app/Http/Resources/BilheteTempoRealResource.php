<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bilhete em tempo real (spec 010, contracts/api.md seção 1). Os dados já vêm montados pelo
 * BilheteTempoReal, com as datas no fuso do pedido.
 *
 * @property array<string, mixed> $resource
 */
class BilheteTempoRealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
