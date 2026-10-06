<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\PorcentagensVendedores;
use App\Models\PorcentagensVendedoresAoVivo;
use App\Models\Usuarios;
use App\Services\AlcanceHierarquia;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

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

    public function update(RegrasCotacaoRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->alcance->garantir_dono($request->user(), $usuario->id);

        $model = $request->validated('tipo') === 'ao_vivo' ? PorcentagensVendedoresAoVivo::class : PorcentagensVendedores::class;
        $registro = $model::firstOrNew(['usuarios_id' => $usuario->id]);
        $registro->valores = $this->regras->aplicar($registro->valores ?? [], $request->validated('valores'), $request->validated('todos'));
        $registro->save();

        return response()->json($this->dados($usuario));
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
