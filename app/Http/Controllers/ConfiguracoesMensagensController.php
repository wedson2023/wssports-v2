<?php

namespace App\Http\Controllers;

use App\Enums\CanalMensagem;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ConfiguracoesMensagensRequest;
use App\Http\Requests\TesteMensagemRequest;
use App\Http\Resources\ConfiguracoesMensagensResource;
use App\Models\Configuracoes;
use App\Services\DisparoMensagens;
use App\Services\HistoricoConfiguracoes;
use App\Services\TextosMensagens;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Arr;

/**
 * Seção "Mensagens" da tela Configurações (spec 025): canais, telefone e e-mail do Admin, confirmação
 * de cadastro e teste dos canais. Só o Admin.
 */
class ConfiguracoesMensagensController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('configuracoes.editar_mensagens')];
    }

    public function show(): ConfiguracoesMensagensResource
    {
        return new ConfiguracoesMensagensResource(Configuracoes::atual());
    }

    public function update(ConfiguracoesMensagensRequest $request, HistoricoConfiguracoes $historico): ConfiguracoesMensagensResource
    {
        $configuracoes = Configuracoes::atual();
        $validados = $request->validated();
        $dados = Arr::except($validados, 'mensagens');

        if (isset($validados['mensagens'])) {
            $dados['mensagens_canais'] = [
                ...($configuracoes->mensagens_canais ?? []),
                ...array_column($validados['mensagens'], 'canal', 'tipo'),
            ];
        }

        $historico->alterar($request->user(), 'mensagens', $configuracoes, $dados);

        return new ConfiguracoesMensagensResource($configuracoes);
    }

    /**
     * Envia na hora para os dados do Admin e mostra o resultado na tela, sem detalhes técnicos.
     */
    public function teste(TesteMensagemRequest $request, DisparoMensagens $disparo, TextosMensagens $textos): JsonResponse
    {
        $canal = CanalMensagem::from($request->input('canal'));
        $configuracoes = Configuracoes::atual();

        if (blank($canal === CanalMensagem::WhatsApp ? $configuracoes->telefone_whatsapp_admin : $configuracoes->email_admin)) {
            return response()->json(['message' => $canal === CanalMensagem::WhatsApp ? 'Informe e salve o telefone do Admin.' : 'Informe e salve o e-mail do Admin.'], 422);
        }

        if (! $disparo->enviar_agora($textos->teste($canal))) {
            return response()->json(['message' => $canal === CanalMensagem::WhatsApp
                ? 'Não foi possível enviar. Confira se o robô está conectado e se o número tem WhatsApp.'
                : 'Não foi possível enviar. Confira o endereço e as credenciais de e-mail do servidor.'], 422);
        }

        return response()->json(['message' => 'Mensagem de teste enviada.']);
    }
}
