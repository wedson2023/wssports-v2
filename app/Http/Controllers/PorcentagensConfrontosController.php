<?php

namespace App\Http\Controllers;

use App\Enums\AlvoRegra;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\PorcentagensConfrontosRequest;
use App\Models\Confrontos;
use App\Models\PorcentagensConfrontos;
use App\Services\AlcanceHierarquia;
use App\Services\HistoricoCotacoes;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cotação de um confronto do pré-jogo: o usuário informa a cotação desejada e o sistema guarda a
 * diferença para a cotação do provedor (valor fixo), por alvo.
 */
class PorcentagensConfrontosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public function __construct(private AlcanceHierarquia $alcance) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('porcentagens_confrontos.editar'),
        ];
    }

    public function show(Request $request, Confrontos $confronto): JsonResponse
    {
        return response()->json($this->dados($request, $confronto));
    }

    public function update(PorcentagensConfrontosRequest $request, Confrontos $confronto, RegrasCotacao $regras, HistoricoCotacoes $historico): JsonResponse
    {
        // o valor do jogo vale só antes do início (spec 016, caso de borda)
        if ($confronto->data_inicio->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['confronto' => 'O jogo já começou; o valor do jogo vale só antes do início.']);
        }

        $alvo = AlvoRegra::from($request->validated('alvo'));
        $dono = $this->alcance->garantir_alvo($request->user(), $alvo, $request->validated('usuarios_id'));
        $diferencas = $regras->diferencas_confronto($confronto, $request->validated('cotacoes'));

        $registro = PorcentagensConfrontos::withTrashed()->firstOrNew([
            'confrontos_id' => $confronto->id,
            'alvo' => $alvo,
            'usuarios_id' => $dono?->id,
        ]);
        $antes = $registro->trashed() ? [] : ($registro->valores ?? []);
        // informar a própria cotação do provedor zera (remove) o valor fixo do código
        $registro->valores = $regras->aplicar($registro->valores ?? [], $diferencas, null);
        $registro->deleted_at = null;

        // gravação e histórico juntos (spec 016, FR-018a)
        DB::transaction(function () use ($registro, $request, $antes, $alvo, $dono, $confronto, $historico) {
            $registro->save();
            $historico->registrar($request->user(), 'confrontos', HistoricoCotacoes::ALTERAR, [
                'alvo' => $alvo,
                'alvo_usuarios_id' => $dono?->id,
                'confrontos_id' => $confronto->id,
            ], $antes, $registro->valores);
        });

        return response()->json($this->dados($request, $confronto));
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Request $request, Confrontos $confronto): array
    {
        $donos = $this->alcance->ids_donos_visiveis($request->user());

        $regras = PorcentagensConfrontos::where('confrontos_id', $confronto->id)
            ->when($donos !== null, fn ($c) => $c->where('alvo', AlvoRegra::Vendedores)->whereIn('usuarios_id', $donos))
            ->orderBy('id')
            ->get()
            ->map(fn (PorcentagensConfrontos $regra) => [
                'id' => $regra->id,
                'alvo' => $regra->alvo,
                'usuarios_id' => $regra->usuarios_id,
                'valores' => (object) ($regra->valores ?? []),
                // cotação resultante, antes das porcentagens do público
                'cotacoes' => (object) collect($regra->valores ?? [])
                    ->map(fn ($valor, string $codigo) => round($confronto->cotacao($codigo) + (float) $valor, 2))
                    ->all(),
            ])
            ->all();

        return [
            'confrontos_id' => $confronto->id,
            'cotacoes_provedor' => (object) ($confronto->cotacoes ?? []),
            'regras' => $regras,
        ];
    }
}
