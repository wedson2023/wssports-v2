<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesBancaRequest;
use App\Http\Resources\ConfiguracoesBancaResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Aba Banca da tela Configurações (spec 021, US1): nome, bilhete, WhatsApp, redes sociais, tema e
 * GTM-ID. Admin e Supervisor; cada salvamento entra no histórico.
 */
class ConfiguracoesBancaController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesBancaResource
    {
        return new ConfiguracoesBancaResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesBancaRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesBancaResource
    {
        $configuracoes = $historico->alterar($request->user(), 'banca', Configuracoes::atual(), $request->validated());

        return new ConfiguracoesBancaResource($configuracoes);
    }
}
