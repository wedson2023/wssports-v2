<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\ApostasRequest;
use App\Http\Resources\ComprovanteApostaResource;
use App\Models\Apostas;
use App\Services\CriacaoApostas;
use App\Support\Apostador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Apostas do vendedor pelo painel: criar e acompanhar a situação (inclusive a análise do ao vivo).
 */
class ApostasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;
    use RespostasApostas;

    private const MAXIMO_CONSULTAS_SITUACAO = 60;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.criar'),
        ];
    }

    public function store(ApostasRequest $request, CriacaoApostas $criacao): JsonResponse
    {
        [$aposta, $repetida] = $criacao->criar(
            Apostador::vendedor($request->user()),
            $request->dados(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->resposta_criacao($aposta, $repetida, mostrar_comissao: true);
    }

    /**
     * Situação de uma aposta do próprio vendedor: comprovante, tempo restante da análise ou motivo
     * da recusa.
     */
    public function situacao(Request $request, string $codigo): JsonResponse
    {
        $this->limitar_tentativas('situacao_aposta|usuario|'.$request->user()->id, self::MAXIMO_CONSULTAS_SITUACAO);

        $aposta = Apostas::buscar_pelo_codigo($codigo)
            ->where('usuarios_id', $request->user()->id)
            ->whereNull('clientes_id')
            ->first();

        abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

        return response()->json(['data' => new ComprovanteApostaResource($aposta, mostrar_comissao: true)]);
    }
}
