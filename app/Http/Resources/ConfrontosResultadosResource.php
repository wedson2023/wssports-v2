<?php

namespace App\Http\Resources;

use App\Models\ConfrontosGols;
use App\Models\ConfrontosResultados;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado de jogo manual depois do lançamento (spec 009), com a quantidade de apostas apuradas.
 *
 * @mixin ConfrontosResultados
 */
class ConfrontosResultadosResource extends JsonResource
{
    public function __construct(ConfrontosResultados $resultado, private int $apostas_apuradas)
    {
        parent::__construct($resultado);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'confrontos_id' => $this->confrontos_id,
            'situacao' => $this->situacao,
            'placar_casa' => $this->placar_casa,
            'placar_fora' => $this->placar_fora,
            'gols_primeiro_tempo_casa' => $this->gols_primeiro_tempo_casa,
            'gols_primeiro_tempo_fora' => $this->gols_primeiro_tempo_fora,
            'gols_segundo_tempo_casa' => $this->gols_segundo_tempo_casa,
            'gols_segundo_tempo_fora' => $this->gols_segundo_tempo_fora,
            'escanteios_casa' => $this->escanteios_casa,
            'escanteios_fora' => $this->escanteios_fora,
            'gols' => $this->confronto->gols->map(fn (ConfrontosGols $gol) => [
                'ordem' => $gol->ordem,
                'nome' => $gol->nome,
                'minuto' => $gol->minuto,
                'tempo_jogo' => $gol->tempo_jogo,
            ]),
            'apostas_apuradas' => $this->apostas_apuradas,
        ];
    }
}
