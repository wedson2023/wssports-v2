<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Funcao;
use App\Models\Afiliados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Alcance da gestão de afiliados (spec 012, FR-050, R-14): o Gerente só vê e age sobre os
 * afiliados de quem é responsável; Admin e Supervisor, sobre todos.
 */
trait GarantirAlcanceAfiliado
{
    /**
     * Afiliado fora do alcance é tratado como inexistente, para não revelar dados de outras equipes.
     */
    private function garantir_alcance(Request $request, Afiliados $afiliado): void
    {
        $usuario = $request->user();

        abort_if(
            $usuario->funcao() === Funcao::Gerente && $afiliado->usuarios_id !== $usuario->id,
            404,
            'Afiliado não encontrado.',
        );
    }

    /**
     * Restringe a consulta aos afiliados do Gerente.
     */
    private function aplicar_alcance(Request $request, Builder $consulta): Builder
    {
        $usuario = $request->user();

        return $usuario->funcao() === Funcao::Gerente
            ? $consulta->where('afiliados.usuarios_id', $usuario->id)
            : $consulta;
    }
}
