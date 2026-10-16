<?php

namespace App\Http\Resources;

use App\Models\CassinosJogos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jogo do cassino no site (spec 011, contracts/api.md §1). O "tem_demo" vem marcado pelo
 * ListagemCassino.
 *
 * @mixin CassinosJogos
 */
class JogoCassinoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'provedor' => $this->provedor?->nome,
            'tipo' => $this->tipo,
            'imagem' => $this->imagem,
            'demo' => (bool) $this->tem_demo,
        ];
    }
}
