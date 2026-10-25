<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Intervalos de sorteio de "ambas marcam" e "ambas não marcam" (spec 021, contracts/api.md §1.3).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesSorteioCotacoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sorteio_ambas_marcam_minimo' => (string) $this->sorteio_ambas_marcam_minimo,
            'sorteio_ambas_marcam_maximo' => (string) $this->sorteio_ambas_marcam_maximo,
            'sorteio_ambas_nao_marcam_minimo' => (string) $this->sorteio_ambas_nao_marcam_minimo,
            'sorteio_ambas_nao_marcam_maximo' => (string) $this->sorteio_ambas_nao_marcam_maximo,
        ];
    }
}
