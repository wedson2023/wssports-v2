<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesPagamentosRequest;
use App\Http\Resources\ConfiguracoesPagamentosResource;
use App\Models\Configuracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações de pagamento do sistema no painel (spec 007, FR-052): limites de depósito e
 * saque, rollover, cashback, modo de saque, meios e tempos.
 */
class ConfiguracoesPagamentosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesPagamentosResource
    {
        return new ConfiguracoesPagamentosResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesPagamentosRequest $request): ConfiguracoesPagamentosResource
    {
        $configuracoes = Configuracoes::atual();
        $configuracoes->update($request->validated());

        return new ConfiguracoesPagamentosResource($configuracoes);
    }
}
