<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dados do próprio afiliado, sem máscara (contracts/api §1.10).
 */
class MeusDadosAfiliadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nome' => $this->nome,
            'login' => $this->login,
            'codigo' => $this->codigo,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'ddi' => $this->ddi,
            'telefone' => $this->telefone,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'endereco' => $this->endereco,
            'pix_tipo_chave' => $this->meio_pagamento?->pix_tipo_chave,
            'pix_chave' => $this->meio_pagamento?->pix_chave,
        ];
    }
}
