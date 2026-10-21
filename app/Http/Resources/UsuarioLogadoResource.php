<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuário do painel logado (spec 014, contracts/api.md §1): nome, função e as seções da Dashboard
 * que ele pode ver, para o painel montar o menu e o login decidir o destino.
 *
 * @mixin Usuarios
 */
class UsuarioLogadoResource extends JsonResource
{
    private const PREFIXO_SECAO = 'dashboard.ver_';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $secoes = array_filter(Funcao::PERMISSOES_DASHBOARD, fn (string $permissao) => Funcao::usuario_pode($this->resource, $permissao));

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'login' => $this->login,
            'funcao' => $this->funcao()?->value,
            'secoes' => array_values(array_map(fn (string $permissao) => substr($permissao, strlen(self::PREFIXO_SECAO)), $secoes)),
            // permissões que o usuário tem e pode usar: o painel mostra menu e botões por elas (spec 015)
            'permissoes' => $this->getAllPermissions()
                ->pluck('name')
                ->filter(fn (string $permissao) => Funcao::usuario_pode($this->resource, $permissao))
                ->values(),
        ];
    }
}
