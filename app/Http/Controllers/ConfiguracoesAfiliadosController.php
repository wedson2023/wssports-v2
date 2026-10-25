<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesAfiliadosRequest;
use App\Http\Resources\ConfiguracoesAfiliadosResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
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

    public function update(ConfiguracoesAfiliadosRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesAfiliadosResource
    {
        $configuracoes = Configuracoes::atual();
        $historico->alterar($request->user(), 'afiliados', $configuracoes, $request->validated());

        return new ConfiguracoesAfiliadosResource($configuracoes);
    }
}
