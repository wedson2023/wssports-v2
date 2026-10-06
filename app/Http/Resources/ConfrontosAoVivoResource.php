<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jogo em andamento no painel, com a indicação de travado.
 */
class ConfrontosAoVivoResource extends JsonResource
{
    /**
     * Configurações lidas uma vez por requisição, para não consultar a cada jogo.
     */
    private static ?Configuracoes $configuracoes = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_externo' => $this->codigo_externo,
            'confrontos_id' => $this->confrontos_id,
            'campeonatos_id' => $this->campeonatos_id,
            'time_casa' => $this->time_casa,
            'escudo_casa' => $this->escudo_casa,
            'time_fora' => $this->time_fora,
            'escudo_fora' => $this->escudo_fora,
            'esporte' => $this->esporte,
            'data_inicio' => $this->data_inicio,
            'placar_casa' => $this->placar_casa,
            'placar_fora' => $this->placar_fora,
            'gols_primeiro_tempo_casa' => $this->gols_primeiro_tempo_casa,
            'gols_primeiro_tempo_fora' => $this->gols_primeiro_tempo_fora,
            'gols_segundo_tempo_casa' => $this->gols_segundo_tempo_casa,
            'gols_segundo_tempo_fora' => $this->gols_segundo_tempo_fora,
            'escanteios_casa' => $this->escanteios_casa,
            'escanteios_fora' => $this->escanteios_fora,
            'minuto' => $this->minuto,
            'cronometro' => $this->cronometro,
            'situacao' => $this->situacao,
            'cotacoes' => (object) ($this->cotacoes ?? []),
            'quantidade_cotacoes' => $this->quantidade_cotacoes,
            'travado' => $this->travado(self::$configuracoes ??= Configuracoes::atual()),
            'ultima_atualizacao_em' => $this->ultima_atualizacao_em,
        ];
    }
}
