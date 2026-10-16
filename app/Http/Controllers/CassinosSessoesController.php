<?php

namespace App\Http\Controllers;

use App\Exceptions\FalhaProvedorCassinoException;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Models\CassinosSessoes;
use App\Services\ProvedorCassino;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Histórico de uma sessão de jogo como o provedor do cassino a registrou (spec 011, FR-033, R-23).
 */
class CassinosSessoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_rodadas.listar')];
    }

    public function historico(CassinosSessoes $sessao, ProvedorCassino $provedor): JsonResponse
    {
        abort_if($sessao->sessao_provedor === null, 422, 'Esta sessão não tem identificação no provedor.');

        try {
            $historico = $provedor->historico_sessao($sessao->sessao_provedor);
        } catch (FalhaProvedorCassinoException) {
            return response()->json(['message' => 'Não foi possível consultar o provedor. Tente novamente.'], 503);
        }

        $sessao->load(['cliente', 'jogo']);

        return response()->json([
            'sessao' => [
                'id' => $sessao->id,
                'cliente' => $sessao->cliente?->nome,
                'jogo' => $sessao->jogo?->nome ?? $sessao->codigo_provedor,
                'sessao_provedor' => $sessao->sessao_provedor,
                'total_apostado' => (string) $sessao->total_apostado,
                'total_ganho' => (string) $sessao->total_ganho,
            ],
            'provedor' => $historico,
        ]);
    }
}
