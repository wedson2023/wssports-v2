<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\EncerrarEspecialRequest;
use App\Http\Resources\EspeciaisResource;
use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use App\Services\EncerramentoEspeciais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Encerrar uma categoria especial com a opção vencedora, ou cancelá-la (spec 006, FR-018).
 */
class EncerramentoEspeciaisController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private EncerramentoEspeciais $encerramento) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('especiais.encerrar')];
    }

    public function encerrar(EncerrarEspecialRequest $request, Especiais $especial): JsonResponse
    {
        $vencedora = EspeciaisOpcoes::findOrFail($request->validated('especiais_opcoes_id'));
        $afetadas = $this->encerramento->encerrar($especial, $vencedora, $request->user());

        return $this->resposta($especial, $afetadas);
    }

    public function cancelar(Request $request, Especiais $especial): JsonResponse
    {
        $afetadas = $this->encerramento->cancelar($especial, $request->user(), (string) $request->ip(), $request->userAgent());

        return $this->resposta($especial, $afetadas);
    }

    private function resposta(Especiais $especial, int $afetadas): JsonResponse
    {
        return (new EspeciaisResource($especial->refresh()->load('opcoes')))
            ->additional(['apostas_afetadas' => $afetadas])
            ->response();
    }
}
