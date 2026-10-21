<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirAlcanceCaixa;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\StoreCaixaLancamentosRequest;
use App\Http\Resources\CaixaLancamentosResource;
use App\Http\Resources\CaixaPrestacoesContasResource;
use App\Models\CaixaLancamentos;
use App\Models\CaixaPrestacoesContas;
use App\Models\Usuarios;
use App\Services\LancamentosCaixa;
use App\Services\PrestacaoContasCaixa;
use App\Support\FusoSistema;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lançamentos e histórico do caixa do vendedor (spec 017, US3, contracts/api.md §2): transações
 * (lançamentos e prestações, inclusive excluídos e desfeitos), lançar e excluir lançamento aberto.
 */
class CaixaLancamentosController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCaixa, GarantirPermissaoCliente;

    public function __construct(
        private readonly LancamentosCaixa $lancamentos,
        private readonly PrestacaoContasCaixa $prestacoes,
    ) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('caixa.ver', only: ['transacoes']),
            self::permissao_cliente('caixa.lancar', only: ['store']),
            self::permissao_cliente('caixa.excluir_lancamento', only: ['destroy']),
        ];
    }

    /**
     * Histórico do vendedor, do mais novo ao mais antigo: lançamentos (pela data do lançamento) e
     * prestações (pelo fim do período), paginado no banco pela união das duas tabelas. O período é
     * opcional aqui, por isso a validação é própria.
     */
    public function transacoes(Request $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);

        $filtros = $request->validate([
            'tipo' => ['nullable', 'in:todos,suprimento,retirada,prestacao'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'tipo.in' => 'O tipo deve ser todos, suprimento, retirada ou prestacao.',
            '*.date_format' => 'A data informada é inválida.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'por_pagina.*' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $tipo = $filtros['tipo'] ?? 'todos';
        $inicio = isset($filtros['de']) ? Carbon::createFromFormat('Y-m-d', $filtros['de'], FusoSistema::FUSO)->startOfDay()->utc() : null;
        $fim = isset($filtros['ate']) ? Carbon::createFromFormat('Y-m-d', $filtros['ate'], FusoSistema::FUSO)->endOfDay()->utc() : null;
        $no_periodo = fn (Builder $consulta, string $coluna) => $consulta
            ->when($inicio, fn (Builder $c) => $c->where($coluna, '>=', $inicio))
            ->when($fim, fn (Builder $c) => $c->where($coluna, '<=', $fim));

        $lancamentos = DB::table('caixa_lancamentos')
            ->where('usuarios_id', $usuario->id)
            ->when($tipo === 'suprimento', fn (Builder $c) => $c->where('tipo', 'Suprimento'))
            ->when($tipo === 'retirada', fn (Builder $c) => $c->where('tipo', 'Retirada'))
            ->tap(fn (Builder $c) => $no_periodo($c, 'ocorrido_em'))
            ->selectRaw("id, 'lancamento' as fonte, ocorrido_em as data");

        $prestacoes = DB::table('caixa_prestacoes_contas')
            ->where('usuarios_id', $usuario->id)
            ->tap(fn (Builder $c) => $no_periodo($c, 'periodo_fim'))
            ->selectRaw("id, 'prestacao' as fonte, periodo_fim as data");

        $uniao = match ($tipo) {
            'prestacao' => $prestacoes,
            'suprimento', 'retirada' => $lancamentos,
            default => $lancamentos->unionAll($prestacoes),
        };

        $pagina = DB::query()->fromSub($uniao, 'transacoes')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate((int) ($filtros['por_pagina'] ?? 20))
            ->withQueryString();

        $itens = collect($pagina->items());
        $ultima = $this->prestacoes->ultima($usuario);
        $solicitante = $request->user();
        $pode_excluir = Funcao::usuario_pode($solicitante, 'caixa.excluir_lancamento');
        $pode_prestar = Funcao::usuario_pode($solicitante, 'caixa.prestar_contas');

        $modelos_lancamentos = CaixaLancamentos::withTrashed()
            ->with(['autor', 'excluido_por_usuario'])
            ->whereIn('id', $itens->where('fonte', 'lancamento')->pluck('id'))
            ->get()
            ->keyBy('id');

        $modelos_prestacoes = CaixaPrestacoesContas::withTrashed()
            ->with(['autor', 'desfeita_por_usuario'])
            ->whereIn('id', $itens->where('fonte', 'prestacao')->pluck('id'))
            ->get()
            ->keyBy('id');

        $dados = $itens->map(function (object $item) use ($modelos_lancamentos, $modelos_prestacoes, $ultima, $solicitante, $pode_excluir, $pode_prestar, $request) {
            if ($item->fonte === 'lancamento') {
                $lancamento = $modelos_lancamentos[$item->id];
                $lancamento->setAttribute('pode_excluir', $pode_excluir && ! $lancamento->trashed() && $lancamento->caixa_prestacoes_contas_id === null);

                return (new CaixaLancamentosResource($lancamento))->toArray($request);
            }

            $prestacao = $modelos_prestacoes[$item->id];
            $prestacao->setAttribute('pode_desfazer', $pode_prestar && $ultima?->id === $prestacao->id && $this->prestacoes->pode_desfazer($prestacao, $solicitante));

            return (new CaixaPrestacoesContasResource($prestacao))->toArray($request);
        })->values();

        return response()->json([...$pagina->toArray(), 'data' => $dados]);
    }

    public function store(StoreCaixaLancamentosRequest $request, Usuarios $usuario): JsonResponse
    {
        $this->garantir_vendedor($request, $usuario);

        $lancamento = $this->lancamentos->lancar(
            $usuario,
            $request->user(),
            $request->tipo(),
            $request->valor(),
            $request->ocorrido_em(),
            $request->validated('observacao'),
        );

        return (new CaixaLancamentosResource($lancamento->load('autor')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, CaixaLancamentos $lancamento): JsonResponse
    {
        $this->garantir_vendedor($request, Usuarios::withTrashed()->findOrFail($lancamento->usuarios_id));

        $this->lancamentos->excluir($lancamento, $request->user());

        return response()->json(null, 204);
    }
}
