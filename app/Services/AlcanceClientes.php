<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Models\Clientes;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Alcance dos clientes no painel (spec 019, FR-003, research R-02): a rede do cliente é a do afiliado
 * que o trouxe, pelo gerente responsável do afiliado. O Admin vê todos (inclusive os sem afiliado e
 * os de afiliado sem gerente); o Supervisor, os clientes dos afiliados dos gerentes abaixo dele
 * (inclusive gerentes excluídos); o Gerente, os dos afiliados dele; o Vendedor, nenhum. Como a rede
 * segue o afiliado, trocar o gerente do afiliado leva os clientes junto, sem coluna nova no cliente.
 */
class AlcanceClientes
{
    private const NAO_ENCONTRADO = 'Cliente não encontrado.';

    /**
     * Gerentes já calculados por usuário, para não refazer a subida da hierarquia na mesma requisição.
     *
     * @var array<int, list<int>|null>
     */
    private array $gerentes_por_usuario = [];

    /**
     * Ids dos gerentes cujos afiliados trazem clientes para a rede do usuário; null = todos (Admin).
     *
     * @return list<int>|null
     */
    public function ids_gerentes(Usuarios $usuario): ?array
    {
        if (array_key_exists($usuario->id, $this->gerentes_por_usuario)) {
            return $this->gerentes_por_usuario[$usuario->id];
        }

        return $this->gerentes_por_usuario[$usuario->id] = match ($usuario->funcao()) {
            Funcao::Admin => null,
            Funcao::Supervisor => Usuarios::withTrashed()
                ->role(Funcao::Gerente->value)
                ->whereIn('id', $usuario->ids_sub_hierarquia_com_excluidos())
                ->pluck('id')
                ->all(),
            Funcao::Gerente => [$usuario->id],
            default => [],
        };
    }

    /**
     * Ids dos clientes da rede, como subconsulta (inclui clientes e afiliados excluídos); null = sem
     * restrição (Admin).
     */
    public function subconsulta_ids(Usuarios $usuario): ?Builder
    {
        $gerentes = $this->ids_gerentes($usuario);

        if ($gerentes === null) {
            return null;
        }

        return DB::table('clientes')
            ->join('afiliados', 'afiliados.id', '=', 'clientes.afiliados_id')
            ->whereIn('afiliados.usuarios_id', $gerentes)
            ->select('clientes.id');
    }

    /**
     * Restringe a consulta aos clientes da rede pela coluna informada (sem restrição para o Admin).
     */
    public function aplicar(Builder|EloquentBuilder $consulta, Usuarios $usuario, string $coluna = 'clientes.id'): Builder|EloquentBuilder
    {
        $subconsulta = $this->subconsulta_ids($usuario);

        return $subconsulta === null ? $consulta : $consulta->whereIn($coluna, $subconsulta);
    }

    /**
     * Se o cliente está na rede do usuário.
     */
    public function contem(Usuarios $usuario, Clientes|int $cliente): bool
    {
        $gerentes = $this->ids_gerentes($usuario);

        if ($gerentes === null) {
            return true;
        }

        $clientes_id = $cliente instanceof Clientes ? $cliente->id : $cliente;

        return $gerentes !== [] && DB::table('clientes')
            ->join('afiliados', 'afiliados.id', '=', 'clientes.afiliados_id')
            ->where('clientes.id', $clientes_id)
            ->whereIn('afiliados.usuarios_id', $gerentes)
            ->exists();
    }

    /**
     * Cliente fora da rede é tratado como inexistente, para não revelar clientes de outras redes.
     */
    public function garantir(Usuarios $usuario, Clientes|int $cliente): void
    {
        abort_unless($this->contem($usuario, $cliente), 404, self::NAO_ENCONTRADO);
    }
}
