<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesEncerramentoRequest;
use App\Http\Resources\ConfiguracoesEncerramentoResource;
use App\Models\Configuracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações gerais do encerramento de aposta no painel (spec 010, FR-036): liga/desliga,
 * margens, mínimos, validade da oferta e limite de parciais.
 */
class ConfiguracoesEncerramentoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesEncerramentoResource
    {
        return new ConfiguracoesEncerramentoResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesEncerramentoRequest $request): ConfiguracoesEncerramentoResource
    {
        $configuracoes = Configuracoes::atual();
        $configuracoes->update($request->validated());

        return new ConfiguracoesEncerramentoResource($configuracoes);
    }
}
