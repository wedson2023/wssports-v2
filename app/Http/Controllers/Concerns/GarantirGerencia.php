<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Usuarios;
use Illuminate\Http\Request;

trait GarantirGerencia
{
    /**
     * Alvo fora da sub-hierarquia de quem solicita (inclusive ele mesmo) é tratado como
     * inexistente, para não revelar dados de outras equipes.
     */
    private function garantir_gerencia(Request $request, Usuarios $alvo): void
    {
        abort_unless($request->user()->gerencia($alvo), 404, 'Usuário não encontrado.');
    }
}
