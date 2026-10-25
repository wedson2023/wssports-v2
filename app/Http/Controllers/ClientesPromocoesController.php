<?php

namespace App\Http\Controllers;

use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Http\Controllers\Concerns\GarantirAlcancePromocao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ClientesPromocoesRequest;
use App\Http\Requests\EstornarPromocaoRequest;
use App\Http\Resources\ClientesPromocoesResource;
use App\Jobs\EstornarPromocao;
use App\Models\ClientesPromocoes;
use App\Models\ClientesTransacoes;
use App\Services\ListagemPromocoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de promoções no painel. Na tela Bônus (spec 020), só as promoções da rede do usuário: o
 * Admin altera todas; Supervisor e Gerente, as dos afiliados da rede, e veem as gerais só para leitura.
 */
class ClientesPromocoesController extends Controller implements HasMiddleware
{
    use GarantirAlcancePromocao, GarantirPermissaoCliente;

    /**
     * Campos que não podem mudar depois que a promoção foi creditada a algum cliente.
     */
    private const CAMPOS_TRAVADOS_APOS_APLICADA = ['valor', 'tipo_ganho', 'categoria', 'modalidade'];

    public function __construct(private readonly ListagemPromocoes $listagem) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes_promocoes.gerenciar', only: ['index', 'show', 'store', 'update', 'destroy']),
            self::permissao_cliente('clientes_promocoes.estornar', only: ['estornar']),
            self::alcance_promocao(),
        ];
    }

    /**
     * Inicia o estorno (rollback) da promoção de todos que a receberam; o processamento
     * acontece em segundo plano, bônus a bônus. A promoção é desativada.
     */
    public function estornar(EstornarPromocaoRequest $request, ClientesPromocoes $promocao): JsonResponse
    {
        $this->garantir_promocao_alteravel($request, $promocao);

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

        return $this->resposta($request, $promocao->refresh())->response()->setStatusCode(202);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->listagem->validar($request);

        $promocoes = $this->listagem->consulta($request->user(), $filtros)
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        $uso = $this->listagem->uso($promocoes->getCollection(), $request->user());
        $promocoes->getCollection()->each(fn (ClientesPromocoes $promocao) => $promocao->setAttribute('uso', $uso[$promocao->id]));

        return ClientesPromocoesResource::collection($promocoes);
    }

    public function show(Request $request, ClientesPromocoes $promocao): ClientesPromocoesResource
    {
        return $this->resposta($request, $promocao);
    }

    public function store(ClientesPromocoesRequest $request): ClientesPromocoesResource
    {
        $promocao = new ClientesPromocoes($request->validated());
        $this->garantir_sem_sobreposicao($promocao);
        $promocao->save();

        return $this->resposta($request, $promocao);
    }

    /**
     * A promoção geral já é recusada para Supervisor e Gerente pelo ClientesPromocoesRequest.
     */
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

        return $this->resposta($request, $promocao);
    }

    /**
     * Só a promoção que ninguém recebeu pode ser excluída (spec 020, FR-019); a aplicada é
     * desativada ou estornada.
     */
    public function destroy(Request $request, ClientesPromocoes $promocao): Response
    {
        $this->garantir_promocao_alteravel($request, $promocao);

        if ($promocao->aplicada() || $promocao->estornada()) {
            throw ValidationException::withMessages([
                'promocao' => 'Promoção já aplicada não pode ser excluída; desative ou estorne.',
            ]);
        }

        $promocao->delete();

        return response()->noContent();
    }

    /**
     * Duas promoções ativas da mesma categoria, modalidade e afiliado (ou duas gerais) não podem ter
     * períodos que se cruzam; afiliados diferentes, ou uma geral e uma de afiliado, podem (spec 020,
     * FR-015: a do afiliado vale antes da geral). Fim nulo = sem fim.
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
            ->when($promocao->afiliados_id === null, fn (Builder $c) => $c->whereNull('afiliados_id'), fn (Builder $c) => $c->where('afiliados_id', $promocao->afiliados_id))
            ->when($promocao->exists, fn (Builder $c) => $c->where('id', '!=', $promocao->id))
            ->when($promocao->data_fim !== null, fn (Builder $c) => $c->where('data_inicio', '<=', $promocao->data_fim))
            ->where(fn (Builder $c) => $c->whereNull('data_fim')->orWhere('data_fim', '>=', $promocao->data_inicio))
            ->first();

        if ($sobreposta !== null) {
            throw ValidationException::withMessages([
                'data_inicio' => "Já existe uma promoção ativa desta categoria e modalidade no período: {$sobreposta->nome} (ID {$sobreposta->id}).",
            ]);
        }
    }

    /**
     * Promoção com o afiliado, o autor do estorno e os números de uso (tela Bônus).
     */
    private function resposta(Request $request, ClientesPromocoes $promocao): ClientesPromocoesResource
    {
        $promocao->loadMissing(['afiliado', 'autor_estorno']);
        $promocao->setAttribute('uso', $this->listagem->uso(collect([$promocao]), $request->user())[$promocao->id]);

        return new ClientesPromocoesResource($promocao);
    }
}
