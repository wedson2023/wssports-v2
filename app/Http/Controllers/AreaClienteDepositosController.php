<?php

namespace App\Http\Controllers;

use App\Enums\MeioDeposito;
use App\Http\Requests\DepositosRequest;
use App\Http\Resources\DepositosResource;
use App\Models\Clientes;
use App\Models\Depositos;
use App\Models\Usuarios;
use App\Services\AcessoAssistido;
use App\Services\CriacaoDepositos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Depósitos do cliente logado (spec 007, US2 e US4).
 */
class AreaClienteDepositosController extends Controller
{
    public function opcoes(Request $request, CriacaoDepositos $depositos): JsonResponse
    {
        return response()->json(['data' => $depositos->opcoes($this->cliente($request))]);
    }

    public function store(DepositosRequest $request, CriacaoDepositos $depositos): JsonResponse
    {
        $cliente = $this->cliente($request);
        $autor = $this->autor($request);
        $aceita_bonus = $request->boolean('aceita_bonus');

        $deposito = $request->validated('meio') === MeioDeposito::CartãoCrédito->value
            ? $depositos->criar_cartao($cliente, $request->valor(), $aceita_bonus, $request->validated('cartao'), $autor, $request->ip())
            : $depositos->criar_pix($cliente, $request->valor(), $aceita_bonus, $autor, $request->ip());

        return (new DepositosResource($deposito))->response()->setStatusCode(201);
    }

    /**
     * Situação do depósito, só para o dono (FR-033).
     */
    public function show(Request $request, string $codigo): DepositosResource
    {
        $deposito = Depositos::where('codigo', $codigo)
            ->where('clientes_id', $this->cliente($request)->id)
            ->first();

        abort_if($deposito === null, 404, 'Depósito não encontrado.');

        return new DepositosResource($deposito);
    }

    private function cliente(Request $request): Clientes
    {
        return $request->user('clientes');
    }

    /**
     * Usuário do painel no acesso assistido (US7); null quando é o próprio cliente.
     */
    private function autor(Request $request): ?Usuarios
    {
        return app(AcessoAssistido::class)->autor($request);
    }
}
