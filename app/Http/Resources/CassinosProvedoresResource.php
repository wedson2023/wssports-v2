<?php

namespace App\Http\Resources;

use App\Models\CassinosProvedores;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Provedor do cassino no painel (spec 011, contracts/api.md §3.3).
 *
 * @mixin CassinosProvedores
 */
class CassinosProvedoresResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'imagem' => $this->url_imagem(),
            'ativo' => $this->ativo,
            'ordem' => $this->ordem,
            'porcentagem_comissao' => (string) $this->porcentagem_comissao,
            'titulos_provedor' => $this->titulos_provedor,
            'quantidade_jogos' => (int) ($this->quantidade_jogos ?? 0),
        ];
    }
}
