<?php

namespace App\Services;

use App\Enums\AlvoRegra;
use App\Enums\Funcao;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Até onde um usuário do painel pode agir nas regras de cotação, nos não permitidos e nas
 * configurações: o próprio usuário e a sua sub-hierarquia; alvos Clientes e Todos só para
 * Admin e Supervisor.
 */
class AlcanceHierarquia
{
    private const MENSAGEM_SEM_PERMISSAO = 'Você não tem permissão para esta ação.';

    /**
     * Dono de uma regra: o próprio solicitante (quando não informado) ou alguém da sub-hierarquia.
     */
    public function garantir_dono(Usuarios $solicitante, ?int $usuarios_id): Usuarios
    {
        if ($usuarios_id === null || $usuarios_id === $solicitante->id) {
            return $solicitante;
        }

        $dono = Usuarios::find($usuarios_id);

        abort_unless($dono !== null && $solicitante->gerencia($dono), 403, self::MENSAGEM_SEM_PERMISSAO);

        return $dono;
    }

    public function e_gestor_geral(Usuarios $usuario): bool
    {
        return in_array($usuario->funcao(), [Funcao::Admin, Funcao::Supervisor], true);
    }

    public function garantir_gestor_geral(Usuarios $usuario): void
    {
        abort_unless($this->e_gestor_geral($usuario), 403, self::MENSAGEM_SEM_PERMISSAO);
    }

    /**
     * Confere o alvo e devolve o dono da regra (só no alvo Vendedores; nos demais, null).
     */
    public function garantir_alvo(Usuarios $solicitante, AlvoRegra $alvo, ?int $usuarios_id, ?int $clientes_id = null): ?Usuarios
    {
        if ($alvo === AlvoRegra::Vendedores) {
            if ($clientes_id !== null) {
                throw ValidationException::withMessages(['clientes_id' => 'O cliente só pode ser informado no alvo Clientes.']);
            }

            return $this->garantir_dono($solicitante, $usuarios_id);
        }

        $this->garantir_gestor_geral($solicitante);

        if ($usuarios_id !== null) {
            throw ValidationException::withMessages(['usuarios_id' => 'O usuário só pode ser informado no alvo Vendedores.']);
        }

        if ($alvo === AlvoRegra::Todos && $clientes_id !== null) {
            throw ValidationException::withMessages(['clientes_id' => 'O cliente só pode ser informado no alvo Clientes.']);
        }

        return null;
    }

    /**
     * Restringe uma consulta de não permitidos aos que valem para o usuário do painel: alvo Todos
     * ou alvo Vendedores com dono nele ou em algum superior.
     */
    public function nao_permitidos_do_usuario(Builder $consulta, Usuarios $usuario): Builder
    {
        return $consulta->where(fn ($alvos) => $alvos->where('alvo', AlvoRegra::Todos)
            ->orWhere(fn ($c) => $c->where('alvo', AlvoRegra::Vendedores)->whereIn('usuarios_id', $usuario->ids_hierarquia_acima())));
    }

    /**
     * Donos cujas regras o solicitante pode ver e alterar; null = todos (Admin e Supervisor).
     *
     * @return list<int>|null
     */
    public function ids_donos_visiveis(Usuarios $solicitante): ?array
    {
        return $this->e_gestor_geral($solicitante) ? null : [$solicitante->id, ...$solicitante->ids_sub_hierarquia()];
    }
}
