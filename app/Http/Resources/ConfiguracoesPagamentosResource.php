<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações de pagamento do painel, sem o percentual de comissão (spec 007, FR-028).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesPagamentosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gateway_pagamento' => $this->gateway_pagamento,
            'depositos_habilitados' => $this->depositos_habilitados,
            'saques_habilitados' => $this->saques_habilitados,
            'deposito_minimo' => $this->deposito_minimo,
            'deposito_maximo' => $this->deposito_maximo,
            'saque_minimo' => $this->saque_minimo,
            'rollover_deposito' => $this->rollover_deposito,
            'rollover_deposito_vezes' => $this->rollover_deposito_vezes,
            'cashback_percentual' => $this->cashback_percentual,
            'modo_saque' => $this->modo_saque,
            'saque_automatico_maximo' => $this->saque_automatico_maximo,
            'meios_deposito' => $this->meios_habilitados(),
            'parcelas_maximas' => $this->parcelas_maximas,
            'minutos_expiracao_pix' => $this->minutos_expiracao_pix,
            'minutos_acesso_assistido' => $this->minutos_acesso_assistido,
        ];
    }
}
