<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Afiliados;
use App\Services\AlcanceClientes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Alcance da gestão de afiliados (spec 012, FR-050, R-14; spec 019, FR-003d): o Gerente só vê e age
 * sobre os afiliados de quem é responsável; o Supervisor, sobre os dos gerentes da rede dele; o
 * Admin, sobre todos (inclusive os sem gerente). É a mesma rede dos clientes (AlcanceClientes).
 */
trait GarantirAlcanceAfiliado
{
    /**
     * Afiliado fora do alcance é tratado como inexistente, para não revelar dados de outras equipes.
     */
    private function garantir_alcance(Request $request, Afiliados $afiliado): void
    {
        $gerentes = app(AlcanceClientes::class)->ids_gerentes($request->user());

        abort_if(
            $gerentes !== null && ! in_array($afiliado->usuarios_id, $gerentes, true),
            404,
            'Afiliado não encontrado.',
        );
    }

    /**
     * Restringe a consulta aos afiliados da rede do usuário.
     */
    private function aplicar_alcance(Request $request, Builder $consulta): Builder
    {
        $gerentes = app(AlcanceClientes::class)->ids_gerentes($request->user());

        return $gerentes === null ? $consulta : $consulta->whereIn('afiliados.usuarios_id', $gerentes);
    }
}
