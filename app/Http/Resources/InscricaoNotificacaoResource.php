<?php

namespace App\Http\Resources;

use App\Models\ClientesInscricoesNotificacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inscrição do aparelho nas notificações (spec 010): só o aparelho e as preferências, nunca as
 * chaves.
 *
 * @mixin ClientesInscricoesNotificacoes
 */
class InscricaoNotificacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'aparelho' => $this->aparelho,
            'receber_gols' => $this->receber_gols,
            'receber_intervalo' => $this->receber_intervalo,
            'receber_fim_jogo' => $this->receber_fim_jogo,
            'receber_resultado_aposta' => $this->receber_resultado_aposta,
            'receber_encerramento_automatico' => $this->receber_encerramento_automatico,
        ];
    }
}
