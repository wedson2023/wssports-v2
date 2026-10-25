<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesAoVivoRequest;
use App\Http\Resources\ConfiguracoesAoVivoResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Seção Ao vivo da aba Operação (spec 021, FR-029 e FR-030): habilitado, trava, permanência, minuto
 * limite e cotação máxima; a trava geral só aparece. Admin e Supervisor; entra no histórico.
 */
class ConfiguracoesAoVivoController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesAoVivoResource
    {
        return new ConfiguracoesAoVivoResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesAoVivoRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesAoVivoResource
    {
        return new ConfiguracoesAoVivoResource($historico->alterar($request->user(), 'ao_vivo', Configuracoes::atual(), $request->validated()));
    }
}
