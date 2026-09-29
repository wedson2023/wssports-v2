<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesConfiguracoesRequest;
use App\Http\Resources\ClientesConfiguracoesResource;
use App\Models\ClientesConfiguracoesPadrao;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Configurações padrão que cada cliente novo recebe. Alterar o padrão não muda
 * as configurações de clientes já cadastrados. Só Admin e Supervisor.
 */
class ClientesConfiguracoesPadraoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.editar_configuracoes_padrao'),
        ];
    }

    public function show(): ClientesConfiguracoesResource
    {
        return new ClientesConfiguracoesResource(ClientesConfiguracoesPadrao::atual());
    }

    public function update(ClientesConfiguracoesRequest $request): ClientesConfiguracoesResource
    {
        $padrao = ClientesConfiguracoesPadrao::atual();
        $padrao->update($request->validated());

        return new ClientesConfiguracoesResource($padrao);
    }
}
