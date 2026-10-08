<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Controllers\Concerns\RespostasApostas;
use App\Http\Requests\ApostasRequest;
use App\Http\Resources\ComprovanteApostaResource;
use App\Http\Resources\SimulacaoApostaResource;
use App\Services\ValidacaoCodigos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Código do visitante no painel do vendedor: simulação (botão "Validar" no front) e validação.
 */
class ValidacaoApostasController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;
    use RespostasApostas;

    private const MAXIMO_POR_VENDEDOR = 20;

    private const MAXIMO_POR_IP = 60;

    public function __construct(private ValidacaoCodigos $validacao) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.validar'),
        ];
    }

    public function show(Request $request, string $codigo): JsonResponse
    {
        $this->limitar($request);

        return response()->json(['data' => new SimulacaoApostaResource($this->validacao->simular($request->user(), $codigo))]);
    }

    public function store(ApostasRequest $request, string $codigo): JsonResponse
    {
        $this->limitar($request);

        // a validação repetida (mesma chave) também responde 200 com o comprovante já validado
        [$aposta] = $this->validacao->validar($request->user(), $codigo, $request->dados(), $request->ip(), $request->userAgent());

        return response()->json(['data' => new ComprovanteApostaResource($aposta->refresh(), mostrar_comissao: true)]);
    }

    /**
     * Anti força bruta nos códigos (FR-042): por vendedor e por IP.
     */
    private function limitar(Request $request): void
    {
        $this->limitar_tentativas('validacao_codigo|usuario|'.$request->user()->id, self::MAXIMO_POR_VENDEDOR);
        $this->limitar_tentativas('validacao_codigo|ip|'.$request->ip(), self::MAXIMO_POR_IP);
    }
}
