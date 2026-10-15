<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Resources\BilheteTempoRealResource;
use App\Models\Apostas;
use App\Models\Clientes;
use App\Models\ClientesAcessosAssistidos;
use App\Services\BilheteTempoReal;
use App\Services\IdentificacaoPublico;
use App\Support\FusoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Bilhete em tempo real pelo código, sem login (spec 010, FR-001 e FR-011). O token do cliente,
 * quando enviado, identifica o dono da aposta (nome completo e encerramento).
 */
class PublicoBilhetesController extends Controller
{
    use RespostasApostas;

    private const MAXIMO_CONSULTAS = 120;

    public function show(Request $request, string $codigo, IdentificacaoPublico $identificacao, BilheteTempoReal $bilhete): JsonResponse
    {
        $this->limitar_tentativas('bilhete_tempo_real|'.$request->ip(), self::MAXIMO_CONSULTAS);

        $aposta = Apostas::buscar_pelo_codigo($codigo)->first();

        abort_if(
            $aposta === null || in_array($aposta->situacao, BilheteTempoReal::situacoes_ocultas(), true),
            404,
            'Bilhete não encontrado ou ainda não validado.',
        );

        $dados = $bilhete->montar(
            $aposta,
            $this->cliente($request, $identificacao),
            $request->query('oferta_anterior'),
            FusoSistema::do_pedido($request->input('fuso_horario')),
        );

        return response()->json(['data' => new BilheteTempoRealResource($dados)]);
    }

    /**
     * Cliente do token; o acesso assistido já encerrado ou vencido não vale como dono (mesma regra
     * do GarantirAcessoCliente).
     */
    private function cliente(Request $request, IdentificacaoPublico $identificacao): ?Clientes
    {
        $publico = $identificacao->identificar($request);

        if (! $publico->e_cliente()) {
            return null;
        }

        try {
            $acesso_id = auth('clientes')->payload()->get('acesso_assistido_id');
        } catch (Throwable) {
            $acesso_id = null;
        }

        if ($acesso_id !== null && ! ClientesAcessosAssistidos::find($acesso_id)?->ativo()) {
            return null;
        }

        return $publico->cliente;
    }
}
