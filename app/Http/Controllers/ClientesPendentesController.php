<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirAlcanceCliente;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\AnteciparPendentesRequest;
use App\Models\Clientes;
use App\Services\AntecipacaoPendentes;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Prêmios e encerramentos de um cliente na janela de segurança e a antecipação deles pelo painel
 * (spec 019, US6). Só Admin e Supervisor (permissão restrita), nos clientes da rede.
 */
class ClientesPendentesController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCliente, GarantirPermissaoCliente;

    public function __construct(private readonly AntecipacaoPendentes $antecipacao) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('clientes.antecipar_pendentes'), self::alcance_cliente()];
    }

    public function show(Clientes $cliente): JsonResponse
    {
        return response()->json($this->antecipacao->pendentes($cliente));
    }

    public function store(AnteciparPendentesRequest $request, Clientes $cliente): JsonResponse
    {
        return response()->json($this->antecipacao->antecipar($cliente, $request->user(), $request->ip()));
    }
}
