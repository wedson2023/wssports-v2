<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\PorcentagensCadeados;
use App\Models\PorcentagensVendedores;
use App\Models\PorcentagensVendedoresAoVivo;
use App\Models\Usuarios;
use App\Services\AlcanceHierarquia;
use App\Services\CadeadosCotacoes;
use App\Services\HistoricoCotacoes;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

/**
 * Porcentagens de ajuste de um usuário do painel (pré-jogo e ao vivo). Só o próprio usuário ou
 * alguém da sub-hierarquia de quem solicita.
 */
class PorcentagensVendedoresController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private AlcanceHierarquia $alcance, private RegrasCotacao $regras) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('porcentagens_vendedores.editar'),
        ];
    }

    public function show(Request $request, Usuarios $usuario): JsonResponse
    {
        $this->alcance->garantir_dono($request->user(), $usuario->id);

        return response()->json($this->dados($usuario));
    }

    public function update(RegrasCotacaoRequest $request, Usuarios $usuario, CadeadosCotacoes $cadeados, HistoricoCotacoes $historico): JsonResponse
    {
        $this->alcance->garantir_dono($request->user(), $usuario->id);

        $tipo = $request->validated('tipo');
        $model = $tipo === 'ao_vivo' ? PorcentagensVendedoresAoVivo::class : PorcentagensVendedores::class;
        $registro = $model::firstOrNew(['usuarios_id' => $usuario->id]);
        $antes = $registro->valores ?? [];

        // cadeado por cotação (spec 016): códigos trancados por um superior não mudam
        $trancados = $cadeados->codigos_trancados($request->user(), PorcentagensCadeados::VENDEDORES, $usuario, $tipo);
        $cadeados->recusar_trancados($request->validated('valores') ?? [], $trancados);

        $registro->valores = $cadeados->manter_trancados(
            $this->regras->aplicar($antes, $request->validated('valores'), $request->validated('todos')),
            $antes,
            $trancados,
        );

        // gravação e histórico juntos (spec 016, FR-018a)
        DB::transaction(function () use ($registro, $request, $antes, $tipo, $usuario, $historico) {
            $registro->save();
            $historico->registrar($request->user(), PorcentagensCadeados::VENDEDORES, HistoricoCotacoes::ALTERAR, [
                'tipo' => $tipo,
                'alvo_usuarios_id' => $usuario->id,
            ], $antes, $registro->valores);
        });

        return response()->json([...$this->dados($usuario), 'pulados' => $request->has('todos') ? count($trancados) : 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Usuarios $usuario): array
    {
        return [
            'usuarios_id' => $usuario->id,
            'pre_jogo' => (object) (PorcentagensVendedores::where('usuarios_id', $usuario->id)->value('valores') ?? []),
            'ao_vivo' => (object) (PorcentagensVendedoresAoVivo::where('usuarios_id', $usuario->id)->value('valores') ?? []),
        ];
    }
}
