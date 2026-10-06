<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confronto do pré-jogo no painel: a cotação do provedor pode aparecer aqui.
 */
class ConfrontosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_externo' => $this->codigo_externo,
            'campeonatos_id' => $this->campeonatos_id,
            'campeonato' => $this->whenLoaded('campeonato', fn () => [
                'id' => $this->campeonato->id,
                'nome' => $this->campeonato->nome,
                'pais' => $this->campeonato->pais,
            ]),
            'time_casa' => $this->time_casa,
            'escudo_casa' => $this->escudo_casa,
            'time_fora' => $this->time_fora,
            'escudo_fora' => $this->escudo_fora,
            'esporte' => $this->esporte,
            'situacao' => $this->situacao,
            'data_inicio' => $this->data_inicio,
            'ativo' => $this->ativo,
            'manual' => $this->manual,
            'odd4_sorteada' => $this->odd4_sorteada,
            'odd7_sorteada' => $this->odd7_sorteada,
            'quantidade_cotacoes' => $this->quantidade_cotacoes,
            'cotacoes' => (object) ($this->cotacoes ?? []),
            'jogadores' => $this->whenLoaded('jogadores', fn () => $this->jogadores->map(fn ($jogador) => [
                'codigo_externo' => $jogador->codigo_externo,
                'nome' => $jogador->nome,
                'opcao' => $jogador->opcao,
                'tipo' => $jogador->tipo,
                'odd' => $jogador->odd,
            ])),
            'nao_permitido' => (bool) ($this->nao_permitido ?? false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
