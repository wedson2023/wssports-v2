<?php

namespace App\Http\Resources;

use App\Enums\SituacaoEncerramento;
use App\Models\ApostasEncerramentos;
use App\Support\FusoSistema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pedido de encerramento para o cliente (spec 010, contracts/api.md, "Encerramento").
 *
 * @mixin ApostasEncerramentos
 */
class EncerramentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fuso = FusoSistema::do_pedido($request->input('fuso_horario'));
        $em_analise = $this->situacao === SituacaoEncerramento::EmAnálise;

        return [
            'chave' => $this->chave_idempotencia,
            'modalidade' => $this->modalidade->value,
            'situacao' => $this->situacao->value,
            'valor' => $this->valor !== null ? (string) $this->valor : null,
            'oferta' => $this->oferta !== null ? (string) $this->oferta : (string) $this->oferta_exibida,
            'valor_apostado_depois' => $this->valor_apostado_depois !== null ? (string) $this->valor_apostado_depois : null,
            'premio_depois' => $this->premio_depois !== null ? (string) $this->premio_depois : null,
            'creditar_em' => $this->creditar_em?->copy()->setTimezone($fuso)->toIso8601String(),
            'motivo_recusa' => $this->motivo_recusa,
            'segundos_restantes' => $em_analise ? $this->segundos_restantes() : null,
        ];
    }

    /**
     * Segundos até o fim do tempo de confirmação do ao vivo (0 quando já passou).
     */
    private function segundos_restantes(): int
    {
        $fim = $this->created_at->copy()->addSeconds($this->aposta->delay_ao_vivo());

        return (int) max(0, ceil(now()->diffInSeconds($fim, false)));
    }
}
