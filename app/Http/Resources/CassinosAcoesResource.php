<?php

namespace App\Http\Resources;

use App\Models\CassinosAcoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tipo de ação do provedor do cassino no painel (spec 011).
 *
 * @mixin CassinosAcoes
 */
class CassinosAcoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acao' => $this->acao,
            'efeito' => $this->efeito,
            'ativo' => $this->ativo,
            'descricao' => $this->descricao,
        ];
    }
}
