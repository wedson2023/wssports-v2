<?php

namespace App\Http\Resources;

use App\Models\CaixaPrestacoesContas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prestação de contas do caixa no histórico do vendedor (spec 017, contracts/api.md §2.1);
 * `pode_desfazer` vem preenchido pelo controller.
 *
 * @mixin CaixaPrestacoesContas
 */
class CaixaPrestacoesContasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chave' => 'prestacao-'.$this->id,
            'id' => $this->id,
            'tipo' => 'Prestação de contas',
            'data' => $this->periodo_fim,
            'periodo_inicio' => $this->periodo_inicio,
            'periodo_fim' => $this->periodo_fim,
            'saldo_anterior' => $this->saldo_anterior,
            'valor_apurado' => $this->valor_apurado,
            'valor_recebido' => $this->valor_recebido,
            'diferenca' => $this->diferenca,
            'em_lote' => $this->lote !== null,
            'observacao' => $this->observacao,
            'registrado_por' => $this->autor ? ['id' => $this->autor->id, 'nome' => $this->autor->nome] : null,
            'desfeita' => $this->trashed(),
            'desfeita_por' => $this->desfeita_por_usuario ? ['id' => $this->desfeita_por_usuario->id, 'nome' => $this->desfeita_por_usuario->nome] : null,
            'desfeita_em' => $this->deleted_at,
            'motivo_desfazer' => $this->motivo_desfazer,
            'pode_desfazer' => (bool) ($this->pode_desfazer ?? false),
        ];
    }
}
