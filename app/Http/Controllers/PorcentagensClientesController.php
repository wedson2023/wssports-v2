<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RegrasCotacaoRequest;
use App\Models\PorcentagensClientes;
use App\Models\PorcentagensClientesAoVivo;
use App\Services\RegrasCotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Porcentagens de ajuste dos clientes do site (pré-jogo e ao vivo): a regra geral (sem cliente,
 * vale também para visitantes) ou a de um cliente, somada à geral. Só Admin e Supervisor.
 */
class PorcentagensClientesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('porcentagens_clientes.editar'),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'clientes_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ], [
            'clientes_id.integer' => 'O cliente deve ser um número inteiro.',
            'clientes_id.exists' => 'Cliente não encontrado.',
        ]);

        return response()->json($this->dados($filtros['clientes_id'] ?? null));
    }

    public function update(RegrasCotacaoRequest $request, RegrasCotacao $regras): JsonResponse
    {
        $clientes_id = $request->validated('clientes_id');
        $model = $request->validated('tipo') === 'ao_vivo' ? PorcentagensClientesAoVivo::class : PorcentagensClientes::class;

        $registro = $model::firstOrNew(['clientes_id' => $clientes_id]);
        $registro->valores = $regras->aplicar($registro->valores ?? [], $request->validated('valores'), $request->validated('todos'));
        $registro->save();

        return response()->json($this->dados($clientes_id));
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(?int $clientes_id): array
    {
        $consulta = fn (string $model) => $model::query()
            ->when($clientes_id === null, fn ($c) => $c->whereNull('clientes_id'), fn ($c) => $c->where('clientes_id', $clientes_id))
            ->value('valores') ?? [];

        return [
            'clientes_id' => $clientes_id,
            'pre_jogo' => (object) $consulta(PorcentagensClientes::class),
            'ao_vivo' => (object) $consulta(PorcentagensClientesAoVivo::class),
        ];
    }
}
