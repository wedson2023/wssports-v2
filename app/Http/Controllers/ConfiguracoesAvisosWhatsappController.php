<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesAvisosWhatsappRequest;
use App\Http\Resources\ConfiguracoesAvisosWhatsappResource;
use App\Models\Configuracoes;
use App\Services\HistoricoConfiguracoes;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Aviso de combinação de risco nova no WhatsApp do Admin (spec 014, FR-048): liga/desliga, telefone
 * e chave do CallMeBot. Sem tela nesta spec (spec de configurações).
 */
class ConfiguracoesAvisosWhatsappController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar')];
    }

    public function show(): ConfiguracoesAvisosWhatsappResource
    {
        return new ConfiguracoesAvisosWhatsappResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesAvisosWhatsappRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesAvisosWhatsappResource
    {
        $configuracoes = Configuracoes::atual();
        $historico->alterar($request->user(), 'avisos_whatsapp', $configuracoes, $request->validated());

        return new ConfiguracoesAvisosWhatsappResource($configuracoes);
    }
}
