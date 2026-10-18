<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Cliente ligado ao afiliado na gestão (contracts/api §2.11): o mesmo do ClientesResource, com a
 * máscara do painel, mais o total depositado.
 */
class ClienteDoAfiliadoResource extends ClientesResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'total_depositado' => AfiliadosResource::valor($this->total_depositado),
        ];
    }
}
