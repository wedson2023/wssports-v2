<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoSaque;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\PagarSaqueRequest;
use App\Http\Requests\RecusarSaqueRequest;
use App\Http\Resources\SaquesResource;
use App\Models\Saques;
use App\Services\PagamentoSaques;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/**
 * Fila de saques no painel (spec 007, FR-038): listar, pagar (baixa manual ou Mercado Pago) e
 * recusar com devolução. Admin e Supervisor.
 */
class SaquesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('saques.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'situacao' => ['nullable', Rule::enum(SituacaoSaque::class)],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'situacao.enum' => 'A situação informada é inválida.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $pendentes = ($filtros['situacao'] ?? null) === SituacaoSaque::Pendente->value;

        $saques = Saques::with(['cliente', 'decidido_por_usuario'])
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('situacao', $filtros['situacao']))
            // a fila de pendentes vem do mais antigo para o mais novo; o histórico, ao contrário
            ->orderBy('created_at', $pendentes ? 'asc' : 'desc')
            ->orderBy('id', $pendentes ? 'asc' : 'desc')
            ->paginate($filtros['por_pagina'] ?? 30, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        return SaquesResource::collection($saques);
    }

    public function pagar(PagarSaqueRequest $request, Saques $saque, PagamentoSaques $pagamento): SaquesResource
    {
        $saque = $request->validated('forma') === 'mercado_pago'
            ? $pagamento->enviar($saque, $request->user())
            : $pagamento->dar_baixa($saque, $request->user());

        return new SaquesResource($saque->load(['cliente', 'decidido_por_usuario']));
    }

    public function recusar(RecusarSaqueRequest $request, Saques $saque, PagamentoSaques $pagamento): SaquesResource
    {
        $saque = $pagamento->recusar($saque, $request->user(), $request->validated('motivo'));

        return new SaquesResource($saque->load(['cliente', 'decidido_por_usuario']));
    }
}
