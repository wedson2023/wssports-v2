<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientesPromocoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'modalidade' => $this->modalidade,
            'categoria' => $this->categoria,
            'tipo_ganho' => $this->tipo_ganho,
            'valor' => $this->valor,
            'rollover' => $this->rollover,
            'valor_minimo_aposta' => $this->valor_minimo_aposta,
            'valor_maximo_aposta' => $this->valor_maximo_aposta,
            'valor_maximo_deposito' => $this->valor_maximo_deposito,
            'valor_maximo_conversao' => $this->valor_maximo_conversao,
            'odd_minima_aposta_simples' => $this->odd_minima_aposta_simples,
            'odd_minima_aposta_multipla' => $this->odd_minima_aposta_multipla,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'ativa' => $this->ativa,
            'vigente' => $this->vigente(),
            'aplicada' => $this->aplicada(),
            'estorno' => [
                'situacao' => $this->estorno_situacao,
                'motivo' => $this->estorno_motivo,
                'autor' => $this->autor_estorno ? ['id' => $this->autor_estorno->id, 'nome' => $this->autor_estorno->nome] : null,
                'iniciado_em' => $this->estorno_iniciado_em,
                'concluido_em' => $this->estorno_concluido_em,
                'total_clientes' => $this->estorno_total_clientes,
                'clientes_processados' => $this->estorno_clientes_processados,
                'valor_total' => $this->estorno_valor_total,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
