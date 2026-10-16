<?php

namespace App\Http\Resources;

use App\Models\CassinosJogos;
use App\Models\CassinosJogosCanais;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jogo do cassino com os canais no painel (spec 011, contracts/api.md §3.1).
 *
 * @mixin CassinosJogos
 */
class CassinosJogosResource extends JsonResource
{
    /**
     * Aviso devolvido na alteração (jogo com o mesmo nome visível em outro provedor).
     */
    private ?string $aviso = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'provedor' => ['id' => $this->provedor?->id, 'nome' => $this->provedor?->nome],
            'tipo' => $this->tipo,
            'imagem' => $this->imagem,
            'aparelhos' => $this->aparelhos,
            'destaque_provedor' => $this->destaque_provedor,
            'popular' => $this->popular,
            'ordem' => $this->ordem,
            'ativo' => $this->ativo,
            'aberturas' => $this->aberturas,
            'removido_em' => $this->removido_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            'canais' => $this->whenLoaded('canais', fn () => $this->canais->map(fn (CassinosJogosCanais $canal) => [
                'id' => $canal->id,
                'canal' => $canal->canal,
                'codigo_provedor' => $canal->codigo_provedor,
                'pre_pago' => $canal->pre_pago,
                'bonus_primeiro' => $canal->bonus_primeiro,
                'prioridade' => $canal->prioridade,
                'ativo' => $canal->ativo,
                'demo' => $canal->demo,
                'flash' => $canal->flash,
                'indisponivel_em' => $canal->indisponivel_em?->copy()->setTimezone('-03:00')->toIso8601String(),
                'motivo_indisponibilidade' => $canal->motivo_indisponibilidade,
                'removido_em' => $canal->removido_em?->copy()->setTimezone('-03:00')->toIso8601String(),
            ])->values()),
            $this->mergeWhen($this->aviso !== null, fn () => ['aviso' => $this->aviso]),
        ];
    }

    public function com_aviso(?string $aviso): self
    {
        $this->aviso = $aviso;

        return $this;
    }
}
