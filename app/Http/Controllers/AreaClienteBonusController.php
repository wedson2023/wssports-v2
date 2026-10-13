<?php

namespace App\Http\Controllers;

use App\Enums\TipoRollover;
use App\Models\Clientes;
use App\Models\ClientesRollovers;
use App\Services\AcessoAssistido;
use App\Services\GestaoBonus;
use App\Services\RegrasAposta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Converter e excluir bônus pelo extrato da tela de créditos (spec 007, US5).
 */
class AreaClienteBonusController extends Controller
{
    public function converter(Request $request, int $rollover, GestaoBonus $bonus): JsonResponse
    {
        $cliente = $this->cliente($request);
        $valor = $bonus->converter($cliente, $this->bonus_do_cliente($cliente, $rollover), app(AcessoAssistido::class)->autor($request));

        return response()->json([
            'message' => 'O valor de '.RegrasAposta::reais($valor).' foi convertido para o seu saldo.',
            'valor' => $valor,
        ]);
    }

    public function excluir(Request $request, int $rollover, GestaoBonus $bonus): JsonResponse
    {
        $cliente = $this->cliente($request);
        $bonus->excluir($cliente, $this->bonus_do_cliente($cliente, $rollover), app(AcessoAssistido::class)->autor($request));

        return response()->json(['message' => 'Bônus excluído com sucesso.']);
    }

    private function bonus_do_cliente(Clientes $cliente, int $id): ClientesRollovers
    {
        $bonus = ClientesRollovers::where('id', $id)
            ->where('clientes_id', $cliente->id)
            ->where('tipo', TipoRollover::Bônus)
            ->first();

        abort_if($bonus === null, 404, 'Bônus não encontrado.');

        return $bonus;
    }

    private function cliente(Request $request): Clientes
    {
        return $request->user('clientes');
    }
}
