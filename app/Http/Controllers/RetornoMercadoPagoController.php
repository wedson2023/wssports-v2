<?php

namespace App\Http\Controllers;

use App\Services\VinculoMercadoPago;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página para onde o Mercado Pago devolve o admin depois da autorização (spec 007, US8).
 */
class RetornoMercadoPagoController extends Controller
{
    public function index(Request $request, VinculoMercadoPago $vinculo): Response
    {
        $resultado = $vinculo->concluir($request->query('code'), $request->query('state'), $request->query('error'));

        return Inertia::render('GatewayReturn', $resultado);
    }
}
