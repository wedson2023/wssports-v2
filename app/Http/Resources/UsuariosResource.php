<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação pública do usuário: nunca expõe password nem remember_token. A listagem (spec 015)
 * acrescenta o superior, as comissões 1/2/3 do vendedor, as quantidades de subordinados ativos e,
 * na Lixeira, quando foi excluído.
 *
 * @mixin Usuarios
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
        $funcao = $this->funcao();

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'funcao' => $funcao?->value,
            'login' => $this->login,
            'telefone' => $this->telefone,
            'endereco' => $this->endereco,
            'ativo' => $this->ativo,
            'usuarios_id' => $this->usuarios_id,
            'superior' => $this->whenLoaded('superior', fn () => $this->superior ? ['id' => $this->superior->id, 'nome' => $this->superior->nome] : null),
            'comissoes' => $this->when(
                $funcao === Funcao::Vendedor && $this->relationLoaded('configuracoes'),
                fn () => $this->configuracoes ? [
                    '1' => (string) $this->configuracoes->comissao_pre_jogo_1,
                    '2' => (string) $this->configuracoes->comissao_pre_jogo_2,
                    '3' => (string) $this->configuracoes->comissao_pre_jogo_3,
                ] : null,
            ),
            // gerente: vendedores diretos; supervisor: vendedores dos gerentes dele
            'quantidade_vendedores' => $this->when(
                isset($this->quantidade_diretos) && in_array($funcao, [Funcao::Gerente, Funcao::Supervisor], true),
                fn () => (int) ($funcao === Funcao::Gerente ? $this->quantidade_diretos : $this->quantidade_netos),
            ),
            'quantidade_gerentes' => $this->when(
                isset($this->quantidade_diretos) && $funcao === Funcao::Supervisor,
                fn () => (int) $this->quantidade_diretos,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->trashed(), fn () => $this->deleted_at),
        ];
    }
}
