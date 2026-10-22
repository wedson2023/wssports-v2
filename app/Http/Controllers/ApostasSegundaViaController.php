<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\ComprovanteApostaResource;
use App\Services\ListagemApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * 2ª via do bilhete no painel (spec 018, R-06, FR-016): só de aposta ativa aguardando resultado,
 * na rede do usuário. A impressão é feita no navegador, pelo mesmo meio do vendedor.
 */
class ApostasSegundaViaController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private readonly ListagemApostas $listagem) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.imprimir_segunda_via'),
        ];
    }

    public function __invoke(Request $request, string $codigo): JsonResponse
    {
        $aposta = $this->listagem->buscar_visivel($request->user(), $codigo);

        abort_unless($this->listagem->aguardando_resultado($aposta), 422, 'A 2ª via só pode ser impressa em apostas aguardando resultado.');

        return response()->json(['data' => new ComprovanteApostaResource($aposta, mostrar_comissao: true)]);
    }
}
