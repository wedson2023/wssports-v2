<?php

namespace App\Services;

use App\Models\ClientesPromocoes;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Alcance das promoções no painel (spec 020, FR-002, research R-06): a rede da promoção é a do afiliado
 * dela, pelo gerente responsável do afiliado (mesma regra de AlcanceClientes). O Admin vê e altera
 * todas; Supervisor e Gerente alteram as dos afiliados da rede e veem as gerais (sem afiliado) só para
 * leitura; as de afiliados de outras redes ou sem gerente ficam invisíveis para eles.
 */
class AlcancePromocoes
{
    private const NAO_ENCONTRADA = 'Promoção não encontrada.';

    private const SO_ADMIN = 'Só o Admin pode alterar promoções gerais.';

    public function __construct(private AlcanceClientes $alcance_clientes) {}

    /**
     * Restringe a consulta às promoções visíveis ao usuário (sem restrição para o Admin).
     */
    public function aplicar(Builder $consulta, Usuarios $usuario, string $coluna = 'clientes_promocoes.afiliados_id'): Builder
    {
        $afiliados = $this->subconsulta_afiliados($usuario);

        if ($afiliados === null) {
            return $consulta;
        }

        return $consulta->where(fn (Builder $visiveis) => $visiveis->whereNull($coluna)->orWhereIn($coluna, $afiliados));
    }

    public function visivel(Usuarios $usuario, ClientesPromocoes $promocao): bool
    {
        return $promocao->afiliados_id === null || $this->afiliado_da_rede($usuario, $promocao->afiliados_id);
    }

    /**
     * O Admin altera todas; os demais, só as de afiliado da rede.
     */
    public function alteravel(Usuarios $usuario, ClientesPromocoes $promocao): bool
    {
        if ($this->alcance_clientes->ids_gerentes($usuario) === null) {
            return true;
        }

        return $promocao->afiliados_id !== null && $this->afiliado_da_rede($usuario, $promocao->afiliados_id);
    }

    /**
     * Promoção fora da rede é tratada como inexistente.
     */
    public function garantir_visivel(Usuarios $usuario, ClientesPromocoes $promocao): void
    {
        abort_unless($this->visivel($usuario, $promocao), 404, self::NAO_ENCONTRADA);
    }

    /**
     * A geral é visível para Supervisor e Gerente, mas só o Admin a altera.
     */
    public function garantir_alteravel(Usuarios $usuario, ClientesPromocoes $promocao): void
    {
        $this->garantir_visivel($usuario, $promocao);
        abort_unless($this->alteravel($usuario, $promocao), 403, self::SO_ADMIN);
    }

    /**
     * Se o afiliado é de um gerente da rede do usuário (inclui afiliados excluídos); o Admin alcança
     * todos, inclusive os sem gerente.
     */
    public function afiliado_da_rede(Usuarios $usuario, int $afiliados_id): bool
    {
        $afiliados = $this->subconsulta_afiliados($usuario);

        if ($afiliados === null) {
            return DB::table('afiliados')->where('id', $afiliados_id)->exists();
        }

        return $afiliados->where('id', $afiliados_id)->exists();
    }

    /**
     * Ids dos afiliados da rede, como subconsulta; null = sem restrição (Admin).
     */
    private function subconsulta_afiliados(Usuarios $usuario): ?QueryBuilder
    {
        $gerentes = $this->alcance_clientes->ids_gerentes($usuario);

        if ($gerentes === null) {
            return null;
        }

        return DB::table('afiliados')->whereIn('usuarios_id', $gerentes)->select('id');
    }
}
