<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesAfiliadosRequest;
use App\Http\Resources\ConfiguracoesAfiliadosResource;
use App\Models\Configuracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Regra de comissão padrão dos afiliados do cadastro público (spec 012, FR-015).
 */
class ConfiguracoesAfiliadosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesAfiliadosResource
    {
        return new ConfiguracoesAfiliadosResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesAfiliadosRequest $request): ConfiguracoesAfiliadosResource
    {
        $configuracoes = Configuracoes::atual();
        $configuracoes->update($request->validated());

        return new ConfiguracoesAfiliadosResource($configuracoes);
    }
}
