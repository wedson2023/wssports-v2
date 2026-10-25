<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesSorteioCotacoesRequest;
use App\Http\Resources\ConfiguracoesSorteioCotacoesResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Seção Sorteio de cotação da aba Operação (spec 021, FR-029): intervalos de "ambas marcam" e "ambas
 * não marcam" quando o provedor não manda a cotação. Admin e Supervisor; entra no histórico.
 */
class ConfiguracoesSorteioCotacoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesSorteioCotacoesResource
    {
        return new ConfiguracoesSorteioCotacoesResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesSorteioCotacoesRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesSorteioCotacoesResource
    {
        return new ConfiguracoesSorteioCotacoesResource($historico->alterar($request->user(), 'sorteio_cotacoes', Configuracoes::atual(), $request->validated()));
    }
}
