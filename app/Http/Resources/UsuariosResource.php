<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação pública do usuário: nunca expõe password, remember_token nem deleted_at.
 */
class UsuariosResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'funcao' => $this->funcao()?->value,
            'login' => $this->login,
            'telefone' => $this->telefone,
            'endereco' => $this->endereco,
            'ativo' => $this->ativo,
            'usuarios_id' => $this->usuarios_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
