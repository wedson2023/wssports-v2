<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoAposta;
use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\ApostaVisitanteRequest;
use App\Http\Requests\ConsultarApostasRequest;
use App\Http\Resources\ComprovanteApostaResource;
use App\Models\Apostas;
use App\Services\CriacaoApostas;
use App\Services\ValidacaoCodigos;
use App\Support\Apostador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Apostas pelo site, sem login: o visitante gera o código e qualquer pessoa consulta uma aposta
 * (ou várias) pelo código. Nunca devolve dados internos.
 */
class PublicoApostasController extends Controller
{
    use RespostasApostas;

    private const MAXIMO_CONSULTAS = 60;

    public function store(ApostaVisitanteRequest $request, CriacaoApostas $criacao): JsonResponse
    {
        [$aposta, $repetida] = $criacao->criar(Apostador::visitante(), $request->dados(), $request->ip(), $request->userAgent());

        return $this->resposta_criacao($aposta, $repetida, mostrar_comissao: false);
    }

    public function show(Request $request, string $codigo, ValidacaoCodigos $validacao): JsonResponse
    {
        $this->limitar_tentativas('consulta_aposta|'.$request->ip(), self::MAXIMO_CONSULTAS);

        $aposta = Apostas::buscar_pelo_codigo($codigo)->first();

        abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

        // a Pendente que passou da validade aparece como Expirada, mesmo antes do comando agendado
        if ($aposta->situacao === SituacaoAposta::Pendente && $validacao->expirada($aposta)) {
            Apostas::whereKey($aposta->id)->where('situacao', SituacaoAposta::Pendente)->update([
                'situacao' => SituacaoAposta::Expirada,
                'motivo_recusa' => 'Código expirado.',
            ]);
            $aposta->refresh();
        }

        return response()->json(['data' => new ComprovanteApostaResource($aposta)]);
    }

    /**
     * Resumo das apostas dos códigos guardados no aparelho; códigos inexistentes são ignorados.
     */
    public function consultar(ConsultarApostasRequest $request): JsonResponse
    {
        $apostas = Apostas::whereIn('codigo', $request->validated('codigos'))
            ->orderByDesc('recebida_em')
            ->get();

        return response()->json(['data' => $apostas->map(fn (Apostas $aposta) => [
            'codigo' => $aposta->codigo,
            'valor' => (string) $aposta->valor,
            'premio' => (string) $aposta->premio,
            'total_a_pagar' => $aposta->total_a_pagar(),
            'situacao' => $aposta->situacao->value,
            'resultado' => $aposta->resultado->value,
            'criada_em' => $aposta->recebida_em->toIso8601String(),
        ])->values()]);
    }
}
