<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CorrigirResultadoEspecialRequest;
use App\Http\Requests\EncerrarEspecialRequest;
use App\Http\Resources\EspeciaisOpcoesResource;
use App\Http\Resources\EspeciaisResource;
use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use App\Services\EncerramentoEspeciais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Encerrar uma categoria especial com uma ou mais vencedoras, cancelá-la, anular uma opção e
 * corrigir o resultado (spec 006, FR-018; spec 024). Corrigir é só de quem tem
 * especiais.corrigir_resultado (Admin).
 */
class EncerramentoEspeciaisController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private EncerramentoEspeciais $encerramento) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('especiais.encerrar', except: ['corrigir']),
            self::permissao_cliente('especiais.corrigir_resultado', only: ['corrigir']),
        ];
    }

    public function encerrar(EncerrarEspecialRequest $request, Especiais $especial): JsonResponse
    {
        $afetadas = $this->encerramento->encerrar($especial, $request->resultado(), $request->user(), (string) $request->ip(), $request->userAgent());

        return $this->resposta($especial, $afetadas);
    }

    public function cancelar(Request $request, Especiais $especial): JsonResponse
    {
        $afetadas = $this->encerramento->cancelar($especial, $request->user(), (string) $request->ip(), $request->userAgent());

        return $this->resposta($especial, $afetadas);
    }

    /**
     * Anula uma opção da categoria Aguardando (spec 024, contracts/api §2.3).
     */
    public function anular(Request $request, Especiais $especial, EspeciaisOpcoes $opcao): JsonResponse
    {
        abort_unless($opcao->especiais_id === $especial->id, 404, 'Categoria ou opção não encontrada.');

        $afetadas = $this->encerramento->anular_opcao($especial, $opcao, $request->user(), (string) $request->ip(), $request->userAgent());

        return (new EspeciaisOpcoesResource($opcao->refresh()))
            ->additional(['apostas_afetadas' => $afetadas])
            ->response();
    }

    /**
     * Troca as vencedoras de uma categoria encerrada e reapura as apostas (spec 024, contracts/api §2.4).
     */
    public function corrigir(CorrigirResultadoEspecialRequest $request, Especiais $especial): JsonResponse
    {
        $afetadas = $this->encerramento->corrigir($especial, $request->resultado(), $request->user(), (string) $request->ip(), $request->userAgent());

        return $this->resposta($especial, $afetadas);
    }

    private function resposta(Especiais $especial, int $afetadas): JsonResponse
    {
        return (new EspeciaisResource($especial->refresh()->load(['opcoes', 'encerrado_por_usuario', 'correcoes.usuario'])))
            ->additional(['apostas_afetadas' => $afetadas])
            ->response();
    }
}
