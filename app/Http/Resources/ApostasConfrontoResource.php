<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Aposta com palpite num jogo, na gaveta da tela Confrontos (spec 023, contracts/api §2.5): só o
 * palpite deste jogo; `palpites` > 1 indica múltipla (quantidade de jogos da aposta).
 */
class ApostasConfrontoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'codigo' => $this->codigo,
            // a consulta é do query builder: a data chega como texto em UTC
            'created_at' => Carbon::parse($this->created_at, 'UTC')->toJSON(),
            'vendedor' => $this->usuarios_id === null ? null : ['id' => $this->usuarios_id, 'nome' => $this->vendedor_nome],
            'cliente' => $this->clientes_id === null ? null : ['id' => $this->clientes_id, 'nome' => $this->cliente_nome],
            'palpites' => (int) $this->jogos,
            'codigo_cotacao' => $this->codigo_cotacao,
            'jogador' => $this->jogador_id === null ? null : ['id' => $this->jogador_id, 'nome' => $this->jogador_nome],
            'valor' => ConfrontosResource::valor($this->valor),
            'premio' => ConfrontosResource::valor($this->premio),
            'resultado' => $this->resultado,
        ];
    }
}
