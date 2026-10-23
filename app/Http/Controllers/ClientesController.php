<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirAlcanceCliente;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\RestaurarClientesRequest;
use App\Http\Requests\UpdateClientesRequest;
use App\Http\Resources\ClientesResource;
use App\Models\Clientes;
use App\Services\FiltrosClientes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

/**
 * Gestão de clientes no painel: listagem, consulta, edição, situação, exclusão e restauração, só dos
 * clientes da rede do usuário (spec 019, FR-003a).
 */
class ClientesController extends Controller implements HasMiddleware
{
    use GarantirAlcanceCliente, GarantirPermissaoCliente;

    /**
     * Dados únicos que recebem o sufixo de exclusão.
     */
    private const DADOS_UNICOS = ['telefone', 'cpf', 'email'];

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('clientes.listar', only: ['index', 'show']),
            self::permissao_cliente('clientes.editar', only: ['update', 'alterar_situacao']),
            self::permissao_cliente('clientes.excluir', only: ['destroy']),
            self::permissao_cliente('clientes.restaurar', only: ['excluidos', 'restaurar']),
        ];
    }

    public function __construct(private readonly FiltrosClientes $filtros) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Clientes::query());
    }

    public function excluidos(Request $request): AnonymousResourceCollection
    {
        return $this->listar($request, Clientes::onlyTrashed());
    }

    public function show(Request $request, Clientes $cliente): ClientesResource
    {
        $this->garantir_cliente($request, $cliente);

        return new ClientesResource($this->com_saldo_liquido($cliente)->load(['configuracoes', 'afiliado']));
    }

    /**
     * Senha só muda quando enviada; saldos e situação têm rotas próprias.
     */
    public function update(UpdateClientesRequest $request, Clientes $cliente): ClientesResource
    {
        $this->garantir_cliente($request, $cliente);

        $cliente->update($request->validated());

        return new ClientesResource($cliente->load(['configuracoes', 'afiliado']));
    }

    /**
     * Ao desativar, os tokens já emitidos deixam de valer imediatamente.
     */
    public function alterar_situacao(Request $request, Clientes $cliente): ClientesResource
    {
        $this->garantir_cliente($request, $cliente);

        $ativo = $request->validate(
            ['ativo' => ['required', 'boolean']],
            ['ativo.required' => 'Informe se o cliente fica ativo.', 'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.'],
        )['ativo'];

        $cliente->update(['ativo' => (bool) $ativo]);

        if (! $cliente->ativo) {
            $cliente->invalidar_tokens();
        }

        return new ClientesResource($cliente->load(['configuracoes', 'afiliado']));
    }

    /**
     * Exclusão lógica com sufixo nos dados únicos, que ficam livres para novos cadastros.
     */
    public function destroy(Request $request, Clientes $cliente): Response
    {
        $this->garantir_cliente($request, $cliente);

        $sufixo = '_deleted_'.now()->timestamp;

        DB::transaction(function () use ($cliente, $sufixo) {
            foreach (self::DADOS_UNICOS as $campo) {
                if ($cliente->{$campo} !== null) {
                    $cliente->{$campo} .= $sufixo;
                }
            }

            $cliente->save();
            $cliente->invalidar_tokens();
            $cliente->delete();
        });

        return response()->noContent();
    }

    /**
     * Devolve os dados únicos originais; se algum já estiver em uso por outro cliente, a
     * restauração só é concluída com um valor novo informado no corpo.
     */
    public function restaurar(RestaurarClientesRequest $request, Clientes $cliente): ClientesResource|JsonResponse
    {
        $this->garantir_cliente($request, $cliente);

        if (! $cliente->trashed()) {
            return response()->json(['message' => 'Cliente não está excluído.'], 422);
        }

        $dados = ['ddi' => $request->validated('ddi', $cliente->ddi)];

        foreach (self::DADOS_UNICOS as $campo) {
            $original = $cliente->{$campo} === null ? null : preg_replace(ClientesResource::SUFIXO_EXCLUSAO, '', $cliente->{$campo});
            $dados[$campo] = $request->has($campo) ? $request->validated($campo) : $original;
        }

        $conflitos = $this->conflitos_de_dados_unicos($dados, $cliente->id);

        if ($conflitos !== []) {
            return response()->json([
                'message' => 'Há dados já em uso por outro cliente.',
                'errors' => array_fill_keys($conflitos, ['Já está em uso por outro cliente; informe um novo valor.']),
            ], 422);
        }

        DB::transaction(function () use ($cliente, $dados) {
            $cliente->forceFill($dados);
            $cliente->restore();
        });

        return new ClientesResource($cliente->load(['configuracoes', 'afiliado']));
    }

    /**
     * @param  array{ddi: string, telefone: ?string, cpf: ?string, email: ?string}  $dados
     * @return list<string> campos em conflito
     */
    private function conflitos_de_dados_unicos(array $dados, int $ignorar_id): array
    {
        $em_uso = fn (string $campo, callable $filtro) => $dados[$campo] !== null
            && Clientes::where('id', '!=', $ignorar_id)->where($filtro)->exists();

        return array_values(array_filter([
            $em_uso('telefone', fn (Builder $c) => $c->where('ddi', $dados['ddi'])->where('telefone', $dados['telefone'])) ? 'telefone' : null,
            $em_uso('cpf', fn (Builder $c) => $c->where('cpf', $dados['cpf'])) ? 'cpf' : null,
            $em_uso('email', fn (Builder $c) => $c->where('email', $dados['email'])) ? 'email' : null,
        ]));
    }

    /**
     * Listagem da rede com busca, filtros, saldo líquido, ordenação e paginação (máximo 100), usada
     * também pelos excluídos (spec 019, FiltrosClientes).
     */
    private function listar(Request $request, Builder $consulta): AnonymousResourceCollection
    {
        $filtros = $this->filtros->validar($request);

        $clientes = $this->filtros->consulta($request->user(), $consulta, $filtros, $request)
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        return ClientesResource::collection($clientes);
    }

    /**
     * O mesmo cliente com o saldo líquido calculado (FR-006), para o detalhe.
     */
    private function com_saldo_liquido(Clientes $cliente): Clientes
    {
        $cliente->saldo_liquido = Clientes::withTrashed()
            ->whereKey($cliente->id)
            ->select(['saldo_liquido' => FiltrosClientes::subconsulta_saldo_liquido()])
            ->value('saldo_liquido');

        return $cliente;
    }
}
