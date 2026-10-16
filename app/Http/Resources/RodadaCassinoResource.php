<?php

namespace App\Http\Resources;

use App\Models\CassinosRodadas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rodada do cassino no detalhe da sessão da Minha Conta (spec 011, FR-028, details_casino do antigo).
 *
 * @mixin CassinosRodadas
 */
class RodadaCassinoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => $this->created_at->copy()->setTimezone('-03:00')->toIso8601String(),
            'trade_id' => $this->trade_id,
            'acao' => $this->acao,
            'efeito' => $this->efeito,
            'carteira' => $this->carteira,
            'saldo_anterior' => (string) $this->saldo_anterior,
            'aposta' => (string) $this->valor_aposta,
            'premio' => (string) $this->valor_premio,
            'cancelada' => $this->cancelada_em !== null,
        ];
    }
}
