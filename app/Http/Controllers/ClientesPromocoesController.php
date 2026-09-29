<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaPromocao;
use App\Enums\ModalidadePromocao;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesPromocoesRequest;
use App\Http\Requests\EstornarPromocaoRequest;
use App\Http\Resources\ClientesPromocoesResource;
use App\Jobs\EstornarPromocao;
use App\Models\ClientesPromocoes;
use App\Models\ClientesTransacoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de promoções no painel.
 */
class ClientesPromocoesController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    /**
     * Campos que não podem mudar depois que a promoção foi creditada a algum cliente.
     */
    private const CAMPOS_TRAVADOS_APOS_APLICADA = ['valor', 'tipo_ganho', 'categoria', 'modalidade'];

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes_promocoes.gerenciar', only: ['index', 'show', 'store', 'update', 'destroy']),
            self::permissao_cliente('clientes_promocoes.estornar', only: ['estornar']),
        ];
    }

    /**
     * Inicia o estorno (rollback) da promoção de todos que a receberam; o processamento
     * acontece em segundo plano. A promoção é desativada.
     */
    public function estornar(EstornarPromocaoRequest $request, ClientesPromocoes $promocao): JsonResponse
    {
        if ($promocao->estornada()) {
            throw ValidationException::withMessages(['promocao' => 'Esta promoção já foi estornada.']);
        }

        DB::transaction(function () use ($request, $promocao) {
            $total_clientes = ClientesTransacoes::where('origem', OrigemTransacao::Promoção)
                ->where('referencia_id', $promocao->id)
                ->distinct()
                ->count('clientes_id');

            $promocao->forceFill([
                'ativa' => false,
                'estorno_situacao' => SituacaoEstorno::EmAndamento,
                'estorno_motivo' => $request->validated('motivo'),
                'estorno_usuarios_id' => $request->user()->id,
                'estorno_iniciado_em' => now(),
                'estorno_total_clientes' => $total_clientes,
                'estorno_clientes_processados' => 0,
                'estorno_valor_total' => 0,
            ])->save();

            // só entra na fila depois que a transação for gravada
            EstornarPromocao::dispatch($promocao->id)->afterCommit();
        });

        return (new ClientesPromocoesResource($promocao->refresh()))->response()->setStatusCode(202);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'ativa' => ['sometimes', 'boolean'],
            'modalidade' => ['sometimes', Rule::enum(ModalidadePromocao::class)],
            'categoria' => ['sometimes', Rule::enum(CategoriaPromocao::class)],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ], [
            'ativa.boolean' => 'O filtro ativa deve ser verdadeiro ou falso.',
            'modalidade.enum' => 'A modalidade informada é inválida.',
            'categoria.enum' => 'A categoria informada é inválida.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);

        $promocoes = ClientesPromocoes::with('autor_estorno')
            ->when(isset($filtros['ativa']), fn (Builder $c) => $c->where('ativa', (bool) $filtros['ativa']))
            ->when(isset($filtros['modalidade']), fn (Builder $c) => $c->where('modalidade', $filtros['modalidade']))
            ->when(isset($filtros['categoria']), fn (Builder $c) => $c->where('categoria', $filtros['categoria']))
            ->orderByDesc('data_inicio')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ClientesPromocoesResource::collection($promocoes);
    }

    public function show(ClientesPromocoes $promocao): ClientesPromocoesResource
    {
        return new ClientesPromocoesResource($promocao);
    }

    public function store(ClientesPromocoesRequest $request): ClientesPromocoesResource
    {
        $promocao = new ClientesPromocoes($request->validated());
        $this->garantir_sem_sobreposicao($promocao);
        $promocao->save();

        return new ClientesPromocoesResource($promocao);
    }

    public function update(ClientesPromocoesRequest $request, ClientesPromocoes $promocao): ClientesPromocoesResource
    {
        if ($promocao->estornada()) {
            throw ValidationException::withMessages(['promocao' => 'Promoção estornada não pode ser alterada.']);
        }

        $promocao->fill($request->validated());

        if ($promocao->isDirty(self::CAMPOS_TRAVADOS_APOS_APLICADA) && $promocao->aplicada()) {
            throw ValidationException::withMessages([
                'promocao' => 'Promoção já aplicada: valor, tipo de ganho, categoria e modalidade não podem mudar.',
            ]);
        }

        $this->garantir_sem_sobreposicao($promocao);
        $promocao->save();

        return new ClientesPromocoesResource($promocao);
    }

    public function destroy(ClientesPromocoes $promocao): Response
    {
        $promocao->delete();

        return response()->noContent();
    }

    /**
     * Duas promoções ativas da mesma categoria e modalidade não podem ter períodos que se
     * cruzam; categorias diferentes podem (fim nulo = sem fim).
     */
    private function garantir_sem_sobreposicao(ClientesPromocoes $promocao): void
    {
        if (! $promocao->ativa) {
            return;
        }

        $sobreposta = ClientesPromocoes::where('ativa', true)
            ->whereNull('estorno_situacao')
            ->where('categoria', $promocao->categoria)
            ->where('modalidade', $promocao->modalidade)
            ->when($promocao->exists, fn (Builder $c) => $c->where('id', '!=', $promocao->id))
            ->when($promocao->data_fim !== null, fn (Builder $c) => $c->where('data_inicio', '<=', $promocao->data_fim))
            ->where(fn (Builder $c) => $c->whereNull('data_fim')->orWhere('data_fim', '>=', $promocao->data_inicio))
            ->exists();

        if ($sobreposta) {
            throw ValidationException::withMessages([
                'data_inicio' => 'Já existe uma promoção ativa desta categoria e modalidade no período.',
            ]);
        }
    }
}
