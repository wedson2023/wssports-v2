<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesApuracaoRequest;
use App\Http\Resources\ConfiguracoesApuracaoResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações gerais da apuração no painel (spec 009, FR-025 e FR-031): remoção automática dos
 * jogos cancelados e janela de pagamento dos prêmios.
 */
class ConfiguracoesApuracaoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesApuracaoResource
    {
        return new ConfiguracoesApuracaoResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesApuracaoRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesApuracaoResource
    {
        $configuracoes = Configuracoes::atual();
        $historico->alterar($request->user(), 'apuracao', $configuracoes, $request->validated());

        return new ConfiguracoesApuracaoResource($configuracoes);
    }
}
