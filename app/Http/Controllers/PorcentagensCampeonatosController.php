<?php

namespace App\Http\Controllers;

use App\Enums\AlvoRegra;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\Campeonatos;
use App\Models\PorcentagensCampeonatos;
use App\Services\AlcanceHierarquia;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Porcentagens de ajuste de um campeonato, por alvo: Clientes e Todos (só Admin e Supervisor) ou
 * Vendedores de um dono (o solicitante ou alguém da sub-hierarquia).
 */
class PorcentagensCampeonatosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private AlcanceHierarquia $alcance) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('porcentagens_campeonatos.editar'),
        ];
    }

    public function show(Request $request, Campeonatos $campeonato): JsonResponse
    {
        return response()->json(['campeonatos_id' => $campeonato->id, 'regras' => $this->regras_visiveis($request, $campeonato)]);
    }

    public function update(RegrasCotacaoRequest $request, Campeonatos $campeonato, RegrasCotacao $regras): JsonResponse
    {
        $alvo = AlvoRegra::from($request->validated('alvo'));
        $dono = $this->alcance->garantir_alvo($request->user(), $alvo, $request->validated('usuarios_id'));

        $registro = PorcentagensCampeonatos::withTrashed()->firstOrNew([
            'campeonatos_id' => $campeonato->id,
            'alvo' => $alvo,
            'usuarios_id' => $dono?->id,
        ]);
        $registro->valores = $regras->aplicar($registro->valores ?? [], $request->validated('valores'), $request->validated('todos'));
        $registro->deleted_at = null;
        $registro->save();

        return response()->json(['campeonatos_id' => $campeonato->id, 'regras' => $this->regras_visiveis($request, $campeonato)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function regras_visiveis(Request $request, Campeonatos $campeonato): array
    {
        $donos = $this->alcance->ids_donos_visiveis($request->user());

        return PorcentagensCampeonatos::where('campeonatos_id', $campeonato->id)
            ->when($donos !== null, fn ($c) => $c->where('alvo', AlvoRegra::Vendedores)->whereIn('usuarios_id', $donos))
            ->orderBy('id')
            ->get()
            ->map(fn (PorcentagensCampeonatos $regra) => [
                'id' => $regra->id,
                'alvo' => $regra->alvo,
                'usuarios_id' => $regra->usuarios_id,
                'valores' => (object) ($regra->valores ?? []),
            ])
            ->all();
    }
}
