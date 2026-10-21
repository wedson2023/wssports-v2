<?php

namespace App\Http\Resources;

use App\Models\CaixaLancamentos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lançamento do caixa no histórico do vendedor (spec 017, contracts/api.md §2.1); `pode_excluir`
 * vem preenchido pelo controller.
 *
 * @mixin CaixaLancamentos
 */
class CaixaLancamentosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chave' => 'lancamento-'.$this->id,
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'data' => $this->ocorrido_em,
            'valor' => $this->valor,
            'ocorrido_em' => $this->ocorrido_em,
            'observacao' => $this->observacao,
            'registrado_por' => $this->autor ? ['id' => $this->autor->id, 'nome' => $this->autor->nome] : null,
            'prestado' => $this->caixa_prestacoes_contas_id !== null,
            'excluido' => $this->trashed(),
            'excluido_por' => $this->excluido_por_usuario ? ['id' => $this->excluido_por_usuario->id, 'nome' => $this->excluido_por_usuario->nome] : null,
            'pode_excluir' => (bool) ($this->pode_excluir ?? false),
        ];
    }
}
