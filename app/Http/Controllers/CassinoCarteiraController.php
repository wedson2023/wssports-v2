<?php

namespace App\Http\Controllers;

use App\Enums\ResultadoChamadaCassino;
use App\Services\AutenticacaoProvedorCassino;
use App\Services\CarteiraCassino;
use App\Services\RegistroChamadasCassino;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Callback do provedor do cassino (spec 011, FR-018, FR-019, contracts/provedor.md §2): endereço
 * público, protegido pela chave do hall. Responde sempre HTTP 200 no formato do provedor, nunca
 * vazio, e registra toda chamada sem a chave.
 */
class CassinoCarteiraController extends Controller
{
    public function __invoke(Request $request, AutenticacaoProvedorCassino $autenticacao, CarteiraCassino $carteira, RegistroChamadasCassino $registro): JsonResponse
    {
        $inicio = hrtime(true);
        $dados = $request->all();

        try {
            $erro = $autenticacao->validar($dados);

            [$resposta, $resultado] = match (true) {
                $erro !== null => [$carteira->falha($erro), ResultadoChamadaCassino::Recusada],
                ($dados['cmd'] ?? null) === 'getBalance' => $carteira->saldo($dados),
                ($dados['cmd'] ?? null) === 'writeBet' => $carteira->lancar($dados),
                default => [$carteira->falha('unknown_command'), ResultadoChamadaCassino::Recusada],
            };
        } catch (Throwable $excecao) {
            Log::error('Cassino: erro no callback do provedor ('.class_basename($excecao).': '.$excecao->getMessage().').');
            [$resposta, $resultado] = [['status' => 'fail', 'error' => 'internal_error'], ResultadoChamadaCassino::Erro];
        }

        $registro->registrar($dados, $resposta, $resultado, $request->ip(), (int) ((hrtime(true) - $inicio) / 1_000_000));

        return response()->json($resposta);
    }
}
