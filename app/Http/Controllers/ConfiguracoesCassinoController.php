<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesCassinoRequest;
use App\Http\Resources\ConfiguracoesCassinoResource;
use App\Models\Configuracoes;
use App\Services\ListagemCassino;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações gerais do cassino no painel (spec 011, FR-031): liga/desliga, demo, data da
 * prestação de contas e retenção do registro de chamadas. Sem bloqueio por GGR.
 */
class ConfiguracoesCassinoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesCassinoResource
    {
        return new ConfiguracoesCassinoResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesCassinoRequest $request): ConfiguracoesCassinoResource
    {
        $configuracoes = Configuracoes::atual();
        $configuracoes->update($request->validated());

        // o demo muda o botão dos jogos na tela inicial em cache
        ListagemCassino::limpar_cache();

        return new ConfiguracoesCassinoResource($configuracoes);
    }
}
