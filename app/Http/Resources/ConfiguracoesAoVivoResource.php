<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações do ao vivo da aba Operação (spec 021, contracts/api.md §1.2), com a trava geral só
 * para leitura.
 *
 * @mixin Configuracoes
 */
class ConfiguracoesAoVivoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ao_vivo_habilitado' => $this->ao_vivo_habilitado,
            'segundos_trava_ao_vivo' => $this->segundos_trava_ao_vivo,
            'minutos_permanencia_ao_vivo' => $this->minutos_permanencia_ao_vivo,
            'minuto_limite_ao_vivo' => $this->minuto_limite_ao_vivo,
            'cotacao_maxima_ao_vivo' => (string) $this->cotacao_maxima_ao_vivo,
            'ao_vivo_travado' => $this->ao_vivo_travado,
            'ao_vivo_travado_em' => $this->ao_vivo_travado_em?->copy()->setTimezone('-03:00')->toIso8601String(),
        ];
    }
}
