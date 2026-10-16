<?php

namespace App\Http\Resources;

use App\Models\CassinosSincronizacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumo de uma carga do catálogo do cassino (spec 011, FR-005).
 *
 * @mixin CassinosSincronizacoes
 */
class CassinosSincronizacoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origem' => $this->origem,
            'usuario' => $this->usuario?->nome,
            'situacao' => $this->situacao,
            'recebidos' => $this->recebidos,
            'jogos_novos' => $this->jogos_novos,
            'canais_novos' => $this->canais_novos,
            'alterados' => $this->alterados,
            'desativados' => $this->desativados,
            'reativados' => $this->reativados,
            'falhas' => $this->falhas,
            'erro' => $this->erro,
            'iniciada_em' => $this->iniciada_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'concluida_em' => $this->concluida_em?->copy()->setTimezone('-03:00')->toIso8601String(),
        ];
    }
}
