<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lançamento do extrato do afiliado (contracts/api §1.7 e §2.10). O cliente só aparece na gestão.
 */
class AfiliadosLancamentosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'evento' => $this->evento,
            'tipo_ganho' => $this->tipo_ganho,
            'base' => $this->base,
            'percentual' => $this->percentual,
            'valor' => $this->valor,
            'ocorrido_em' => $this->ocorrido_em,
            'prestado' => $this->afiliados_prestacoes_contas_id !== null,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente === null ? null : [
                'id' => $this->cliente->id,
                'nome' => $this->cliente->nome,
            ]),
        ];
    }
}
