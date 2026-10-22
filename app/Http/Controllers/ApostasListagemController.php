<?php

namespace App\Http\Controllers;

use App\Enums\Funcao;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\ApostasFiltrosRequest;
use App\Http\Resources\ApostasListaResource;
use App\Http\Resources\ComprovanteApostaResource;
use App\Models\Apostas;
use App\Models\Usuarios;
use App\Services\ExportacaoApostas;
use App\Services\ListagemApostas;
use App\Support\FusoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Lista de apostas do painel (spec 018, contracts/api.md §1 e §2.1): página da lista, totais do
 * filtro, vendedores para o filtro e o bilhete com as ações permitidas. Alcance e filtros pelo
 * ListagemApostas; cancelar e editar continuam nas rotas da spec 004.
 */
class ApostasListagemController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    private const TAMANHO_BUSCA = 60;

    public function __construct(private readonly ListagemApostas $listagem) {}

    public static function middleware(): array
    {
        return [
            self::permissao_cliente('apostas.listar'),
        ];
    }

    public function index(ApostasFiltrosRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();
        $pagina = $this->listagem->lista($usuario, $request->filtros(), $request->por_pagina());

        $pagina->getCollection()->each(function (Apostas $aposta) use ($usuario) {
            $aposta->setAttribute('acoes', $this->listagem->acoes_da_linha($usuario, $aposta));
            $aposta->setAttribute('situacao_vendedor', $this->listagem->situacao_vendedor($aposta->vendedor_ativo, $aposta->vendedor_excluido_em));
        });

        return ApostasListaResource::collection($pagina->withQueryString());
    }

    public function resumo(ApostasFiltrosRequest $request): JsonResponse
    {
        return response()->json([
            'totais' => $this->listagem->resumo($request->user(), $request->filtros()),
            'periodo' => ['de' => $request->validated('de'), 'ate' => $request->validated('ate')],
            'limite_planilha' => ExportacaoApostas::MAXIMO_LINHAS,
        ]);
    }

    /**
     * Vendedores da rede para o filtro; o Vendedor não filtra por vendedor.
     */
    public function vendedores(Request $request): JsonResponse
    {
        abort_if($request->user()->funcao() === Funcao::Vendedor, 403, 'Você não tem permissão para esta ação.');

        $busca = $request->validate(
            ['busca' => ['nullable', 'string', 'max:'.self::TAMANHO_BUSCA]],
            ['busca.*' => 'A busca deve ter no máximo '.self::TAMANHO_BUSCA.' caracteres.'],
        )['busca'] ?? null;

        return response()->json(['data' => $this->listagem->vendedores($request->user(), $busca)]);
    }

    /**
     * Bilhete com a comissão e as ações que o usuário pode usar nele.
     */
    public function show(Request $request, string $codigo): JsonResponse
    {
        $usuario = $request->user();
        $aposta = $this->listagem->buscar_visivel($usuario, $codigo);

        return response()->json([
            'data' => new ComprovanteApostaResource($aposta, mostrar_comissao: true),
            'painel' => [
                'cancelada_em' => $aposta->cancelada_em?->copy()->setTimezone(FusoSistema::FUSO)->toIso8601String(),
                'cancelada_por' => $aposta->cancelada_por ? Usuarios::withTrashed()->whereKey($aposta->cancelada_por)->value('nome') : null,
                'acoes' => $this->listagem->acoes_do_bilhete($usuario, $aposta),
            ],
        ]);
    }
}
