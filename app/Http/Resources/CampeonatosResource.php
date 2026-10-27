<?php

namespace App\Http\Resources;

use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Campeonato no painel; na aba Campeonatos da tela Confrontos (spec 023) traz também os jogos, as
 * restrições por rede e as ações que a linha mostra.
 */
class CampeonatosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_externo' => $this->codigo_externo,
            'nome' => $this->nome,
            'pais' => $this->pais,
            'bandeira' => $this->bandeira,
            'ativo' => $this->ativo,
            'favorito' => $this->favorito,
            'manual' => $this->manual,
            // se há um não permitido que vale para quem consulta
            'nao_permitido' => (bool) ($this->nao_permitido ?? false),
            // campos da aba Campeonatos (spec 023): só quando a consulta os trouxe
            'quantidade_jogos' => $this->when($this->tem_atributo('quantidade_jogos'), fn () => (int) $this->quantidade_jogos),
            'total_jogos' => $this->when($this->tem_atributo('total_jogos'), fn () => (int) $this->total_jogos),
            'restricoes' => $this->when($this->tem_atributo('nao_permitido_proprio_id'), fn () => [
                'nao_permitido_proprio_id' => $this->nao_permitido_proprio_id,
                'redes_desativadas' => $this->tem_atributo('redes_desativadas') ? (int) $this->redes_desativadas : null,
            ]),
            'acoes' => $this->when($this->tem_atributo('nao_permitido_proprio_id'), fn () => $this->acoes($request->user())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Ações que a linha mostra, pela permissão, pela função e pelo campeonato (contracts/api §1.5).
     *
     * @return array<string, bool>
     */
    private function acoes(Usuarios $usuario): array
    {
        $pode = fn (string $permissao) => Funcao::usuario_pode($usuario, $permissao);
        $gestor_geral = in_array($usuario->funcao(), [Funcao::Admin, Funcao::Supervisor], true);

        return [
            'alterar_situacao' => $gestor_geral && $pode('campeonatos.alterar_situacao'),
            'desativar_rede' => ! $gestor_geral && $pode('campeonatos_nao_permitidos.gerenciar'),
            'favoritar' => $pode('campeonatos.favoritar'),
            'alterar_cotacoes' => $pode('porcentagens_campeonatos.editar'),
            'editar' => $this->manual && $pode('campeonatos.editar'),
            'excluir' => $this->manual && $pode('campeonatos.excluir'),
            'adicionar_jogo' => $this->manual && $pode('confrontos.cadastrar'),
        ];
    }

    private function tem_atributo(string $nome): bool
    {
        return array_key_exists($nome, $this->resource->getAttributes());
    }
}
