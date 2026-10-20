<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Usuarios;
use App\Support\FusoSistema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Alcance da Dashboard por nível (spec 014, R-03): o Admin vê a banca toda; Supervisor e Gerente, os
 * vendedores da sua sub-hierarquia (inclusive os excluídos, para as vendas do dia continuarem na
 * rede); o Vendedor, só ele. Também define o período do dia em Brasília (R-04).
 */
class AlcanceDashboard
{
    /**
     * Ids dos usuários cujas apostas o usuário vê; null = banca toda (Admin).
     *
     * @return list<int>|null
     */
    public function ids_vendedores(Usuarios $usuario): ?array
    {
        return match ($usuario->funcao()) {
            Funcao::Admin => null,
            Funcao::Vendedor => [$usuario->id],
            default => $this->ids_sub_hierarquia_com_excluidos($usuario),
        };
    }

    /**
     * Restringe a consulta aos ids do alcance (sem restrição quando null).
     */
    public function aplicar(Builder|EloquentBuilder $consulta, ?array $ids, string $coluna = 'usuarios_id'): Builder|EloquentBuilder
    {
        return $ids === null ? $consulta : $consulta->whereIn($coluna, $ids);
    }

    /**
     * Início e fim do dia atual em Brasília, em UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodo_do_dia(): array
    {
        return [FusoSistema::inicio_do_dia(), FusoSistema::fim_do_dia_em(0)];
    }

    /**
     * De ontem (início do dia em Brasília) até o fim de hoje, em UTC: período da Combinação de risco
     * e de Apostas por um jogo, como no antigo.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodo_de_ontem_e_hoje(): array
    {
        return [FusoSistema::inicio_do_dia()->subDay(), FusoSistema::fim_do_dia_em(0)];
    }

    /**
     * Mesma subida de Usuarios::ids_sub_hierarquia, mas sem ignorar os excluídos.
     *
     * @return list<int>
     */
    private function ids_sub_hierarquia_com_excluidos(Usuarios $usuario): array
    {
        $ids_encontrados = [];
        $ids_nivel_atual = [$usuario->id];

        while ($ids_nivel_atual !== []) {
            $ids_nivel_atual = Usuarios::withTrashed()->whereIn('usuarios_id', $ids_nivel_atual)->pluck('id')->all();
            $ids_encontrados = [...$ids_encontrados, ...$ids_nivel_atual];
        }

        return $ids_encontrados;
    }
}
