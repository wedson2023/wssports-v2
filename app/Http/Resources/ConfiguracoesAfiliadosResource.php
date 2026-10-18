<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regra de comissão padrão do cadastro público de afiliados (contracts/api §3.1).
 */
class ConfiguracoesAfiliadosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'afiliados_tipo_ganho_padrao' => $this->afiliados_tipo_ganho_padrao,
            'afiliados_percentual_padrao' => $this->afiliados_percentual_padrao,
        ];
    }
}
