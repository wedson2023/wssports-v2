<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações gerais do Criar Aposta no painel (spec 008).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesCriarApostaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'criar_aposta_habilitado' => $this->criar_aposta_habilitado,
            'desconto_criar_aposta' => $this->desconto_criar_aposta,
            'quantidade_minima_criar_aposta' => $this->quantidade_minima_criar_aposta,
            'quantidade_maxima_criar_aposta' => $this->quantidade_maxima_criar_aposta,
            'liquidacao_criar_aposta' => $this->liquidacao_criar_aposta?->value,
        ];
    }
}
