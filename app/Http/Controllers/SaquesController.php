<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\PagarSaqueRequest;
use App\Http\Requests\RecusarSaqueRequest;
use App\Http\Resources\SaquesResource;
use App\Models\Saques;
use App\Services\AlcanceClientes;
use App\Services\ListagemSaques;
use App\Services\PagamentoSaques;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Fila de saques no painel (spec 007, FR-038): listar, pagar (baixa manual ou Mercado Pago) e
 * recusar com devolução. Admin e Supervisor, só os saques dos clientes da rede (spec 019, R-07), com
 * busca, período e os cartões do resumo.
 */
class SaquesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(
        private readonly ListagemSaques $listagem,
        private readonly AlcanceClientes $alcance,
    ) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('saques.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->listagem->validar($request);

        $saques = $this->listagem->consulta($request->user(), $filtros)
            ->paginate($filtros['por_pagina'] ?? 30, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        return SaquesResource::collection($saques);
    }

    /**
     * Cartões da aba Saques: pendentes, processando e, com período, pagos (spec 019, FR-024).
     */
    public function resumo(Request $request): JsonResponse
    {
        return response()->json($this->listagem->resumo($request->user(), $this->listagem->validar($request)));
    }

    public function pagar(PagarSaqueRequest $request, Saques $saque, PagamentoSaques $pagamento): SaquesResource
    {
        $this->garantir_da_rede($request, $saque);

        $saque = $request->validated('forma') === 'mercado_pago'
            ? $pagamento->enviar($saque, $request->user())
            : $pagamento->dar_baixa($saque, $request->user());

        return new SaquesResource($saque->load(['cliente', 'decidido_por_usuario']));
    }

    public function recusar(RecusarSaqueRequest $request, Saques $saque, PagamentoSaques $pagamento): SaquesResource
    {
        $this->garantir_da_rede($request, $saque);

        $saque = $pagamento->recusar($saque, $request->user(), $request->validated('motivo'));

        return new SaquesResource($saque->load(['cliente', 'decidido_por_usuario']));
    }

    /**
     * Saque de cliente fora da rede é tratado como inexistente.
     */
    private function garantir_da_rede(Request $request, Saques $saque): void
    {
        abort_unless($this->alcance->contem($request->user(), $saque->clientes_id), 404, 'Saque não encontrado.');
    }
}
