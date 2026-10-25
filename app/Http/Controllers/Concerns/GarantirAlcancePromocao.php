<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClientesPromocoes;
use App\Services\AlcancePromocoes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Alcance das promoções nos controllers do painel (spec 020, FR-002): fora da rede, a promoção é
 * tratada como inexistente; a geral é visível, mas só o Admin a altera.
 */
trait GarantirAlcancePromocao
{
    /**
     * Middleware de controller que confere a promoção da rota ({promocao}) na rede do usuário.
     */
    protected static function alcance_promocao(): Middleware
    {
        return new Middleware(function (Request $request, Closure $next) {
            $promocao = $request->route('promocao');

            if ($promocao instanceof ClientesPromocoes) {
                app(AlcancePromocoes::class)->garantir_visivel($request->user(), $promocao);
            }

            return $next($request);
        });
    }

    private function garantir_promocao_alteravel(Request $request, ClientesPromocoes $promocao): void
    {
        app(AlcancePromocoes::class)->garantir_alteravel($request->user(), $promocao);
    }
}
