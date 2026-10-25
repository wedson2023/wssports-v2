<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesCriarApostaRequest;
use App\Http\Resources\ConfiguracoesCriarApostaResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações gerais do Criar Aposta no painel (spec 008, FR-013 e FR-014): chave, desconto,
 * mínimo e máximo de palpites por bloco e regra de liquidação.
 */
class ConfiguracoesCriarApostaController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesCriarApostaResource
    {
        return new ConfiguracoesCriarApostaResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesCriarApostaRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesCriarApostaResource
    {
        $configuracoes = Configuracoes::atual();
        $historico->alterar($request->user(), 'criar_aposta', $configuracoes, $request->validated());

        return new ConfiguracoesCriarApostaResource($configuracoes);
    }
}
