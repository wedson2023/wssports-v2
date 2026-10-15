<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações gerais da apuração no painel (spec 009).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesApuracaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'remover_confronto_cancelado' => $this->remover_confronto_cancelado,
            'minutos_pagamento_premio' => $this->minutos_pagamento_premio,
        ];
    }
}
