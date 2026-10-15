<?php

namespace App\Http\Resources;

use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações gerais do encerramento de aposta no painel (spec 010).
 *
 * @mixin Configuracoes
 */
class ConfiguracoesEncerramentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'encerramento_habilitado' => $this->encerramento_habilitado,
            'encerramento_parcial_habilitado' => $this->encerramento_parcial_habilitado,
            'encerramento_automatico_habilitado' => $this->encerramento_automatico_habilitado,
            'encerramento_jogos_nao_iniciados' => $this->encerramento_jogos_nao_iniciados,
            'margem_encerramento_pre_jogo' => (string) $this->margem_encerramento_pre_jogo,
            'margem_encerramento_ao_vivo' => (string) $this->margem_encerramento_ao_vivo,
            'valor_minimo_encerramento' => (string) $this->valor_minimo_encerramento,
            'percentual_minimo_encerramento' => (string) $this->percentual_minimo_encerramento,
            'segundos_validade_oferta' => $this->segundos_validade_oferta,
            'limite_encerramentos_parciais' => $this->limite_encerramentos_parciais,
        ];
    }
}
