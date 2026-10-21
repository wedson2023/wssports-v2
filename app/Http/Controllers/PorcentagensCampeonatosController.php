<?php

namespace App\Http\Controllers;

use App\Enums\AlvoRegra;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\Campeonatos;
use App\Models\PorcentagensCadeados;
use App\Models\PorcentagensCampeonatos;
use App\Services\AlcanceHierarquia;
use App\Services\CadeadosCotacoes;
use App\Services\HistoricoCotacoes;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

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

    public function update(
        RegrasCotacaoRequest $request,
        Campeonatos $campeonato,
        RegrasCotacao $regras,
        CadeadosCotacoes $cadeados,
        HistoricoCotacoes $historico,
    ): JsonResponse {
        $alvo = AlvoRegra::from($request->validated('alvo'));
        $dono = $this->alcance->garantir_alvo($request->user(), $alvo, $request->validated('usuarios_id'));

        $registro = PorcentagensCampeonatos::withTrashed()->firstOrNew([
            'campeonatos_id' => $campeonato->id,
            'alvo' => $alvo,
            'usuarios_id' => $dono?->id,
        ]);
        $antes = $registro->trashed() ? [] : ($registro->valores ?? []);

        // cadeado por cotação (spec 016): só no alvo Vendedores de um dono
        $trancados = $dono !== null
            ? $cadeados->codigos_trancados($request->user(), PorcentagensCadeados::CAMPEONATOS, $dono, null, $campeonato->id)
            : [];
        $cadeados->recusar_trancados($request->validated('valores') ?? [], $trancados);

        $registro->valores = $cadeados->manter_trancados(
            $regras->aplicar($registro->valores ?? [], $request->validated('valores'), $request->validated('todos')),
            $registro->valores ?? [],
            $trancados,
        );
        $registro->deleted_at = null;

        // gravação e histórico juntos (spec 016, FR-018a)
        DB::transaction(function () use ($registro, $request, $antes, $alvo, $dono, $campeonato, $historico) {
            $registro->save();
            $historico->registrar($request->user(), PorcentagensCadeados::CAMPEONATOS, HistoricoCotacoes::ALTERAR, [
                'alvo' => $alvo,
                'alvo_usuarios_id' => $dono?->id,
                'campeonatos_id' => $campeonato->id,
            ], $antes, $registro->valores);
        });

        return response()->json([
            'campeonatos_id' => $campeonato->id,
            'regras' => $this->regras_visiveis($request, $campeonato),
            'pulados' => $request->has('todos') ? count($trancados) : 0,
        ]);
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
