<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configurações de um vendedor, com o nome dele.
 */
class UsuariosConfiguracoesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'usuarios_id' => $this->usuarios_id,
            'nome' => $this->usuario?->nome,
            'esportes_permitidos' => $this->esportes_permitidos,
            'apostar_outros_esportes' => $this->apostar_outros_esportes,
            'ao_vivo_habilitado' => $this->ao_vivo_habilitado,
            'minuto_limite_ao_vivo' => $this->minuto_limite_ao_vivo,
            'cotacao_maxima_ao_vivo' => $this->cotacao_maxima_ao_vivo,
            'updated_at' => $this->updated_at,
        ];
    }
}
