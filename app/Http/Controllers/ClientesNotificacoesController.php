<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\NotificacaoManualRequest;
use App\Http\Resources\ClientesNotificacoesEnviosResource;
use App\Models\Configuracoes;
use App\Services\NotificacoesManuais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Notificação push manual da tela Clientes (spec 019, US7): limite do dia, contagem dos alcançados e
 * envio. Só Admin e Supervisor (permissão restrita); alterar o limite, só o Admin.
 */
class ClientesNotificacoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly NotificacoesManuais $manuais) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('clientes.enviar_notificacao')];
    }

    public function limite(Request $request): JsonResponse
    {
        return response()->json([
            ...$this->manuais->situacao_limite(),
            'pode_alterar' => $request->user()->funcao() === Funcao::Admin,
        ]);
    }

    public function alterar_limite(Request $request): JsonResponse
    {
        abort_unless($request->user()->funcao() === Funcao::Admin, 403, 'Você não tem permissão para esta ação.');

        $limite = $request->validate(
            ['limite' => ['required', 'integer', 'between:1,50']],
            ['limite.*' => 'O limite deve ser um número de 1 a 50.'],
        )['limite'];

        Configuracoes::atual()->update(['limite_notificacoes_manuais_dia' => (int) $limite]);

        return $this->limite($request);
    }

    public function alcance(NotificacaoManualRequest $request): JsonResponse
    {
        return response()->json(['alcancados' => $this->manuais->contar($request->user(), $request->validated())]);
    }

    public function store(NotificacaoManualRequest $request): JsonResponse
    {
        $envio = $this->manuais->enviar($request->user(), $request->validated());

        return (new ClientesNotificacoesEnviosResource($envio))
            ->additional(['restantes' => $this->manuais->situacao_limite()['restantes']])
            ->response()
            ->setStatusCode(201);
    }
}
