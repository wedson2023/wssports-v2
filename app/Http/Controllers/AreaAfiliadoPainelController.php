<?php

namespace App\Http\Controllers;

use App\Http\Resources\AfiliadosLancamentosResource;
use App\Http\Resources\AfiliadosPrestacoesContasResource;
use App\Http\Resources\ClienteIndicadoResource;
use App\Http\Resources\PainelAfiliadoResource;
use App\Models\Afiliados;
use App\Services\ListagemAfiliados;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Painel do afiliado (/afiliados, spec 012, US7): só os dados do próprio afiliado do token.
 */
class AreaAfiliadoPainelController extends Controller
{
    /**
     * Itens por página do painel quando não informado (máximo 100).
     */
    private const POR_PAGINA = 30;

    public function __construct(private ListagemAfiliados $listagem) {}

    public function painel(Request $request): PainelAfiliadoResource
    {
        $afiliado = $this->afiliado($request)
            ->load(['comissao_vigente', 'ultima_prestacao'])
            ->loadCount('clientes')
            ->loadSum(['lancamentos as valor_a_receber' => fn ($consulta) => $consulta->emAberto()], 'valor');

        return new PainelAfiliadoResource($afiliado);
    }

    public function extrato(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate(ListagemAfiliados::regras_extrato(), ListagemAfiliados::mensagens_extrato());

        $lancamentos = $this->listagem
            ->filtrar_lancamentos($this->afiliado($request)->lancamentos(), $filtros)
            ->paginate($filtros['por_pagina'] ?? self::POR_PAGINA)
            ->withQueryString();

        return AfiliadosLancamentosResource::collection($lancamentos);
    }

    public function clientes(Request $request): AnonymousResourceCollection
    {
        $por_pagina = $request->validate(['por_pagina' => ['nullable', 'integer', 'between:1,100']], ListagemAfiliados::mensagens_extrato())['por_pagina'] ?? self::POR_PAGINA;

        $clientes = $this->afiliado($request)->clientes()
            ->select(['clientes.id', 'clientes.nome', 'clientes.created_at'])
            ->selectSub(ListagemAfiliados::subconsulta_total_depositado(), 'total_depositado')
            ->latest('clientes.created_at')
            ->latest('clientes.id')
            ->paginate($por_pagina)
            ->withQueryString();

        return ClienteIndicadoResource::collection($clientes);
    }

    public function prestacoes(Request $request): AnonymousResourceCollection
    {
        $por_pagina = $request->validate(['por_pagina' => ['nullable', 'integer', 'between:1,100']], ListagemAfiliados::mensagens_extrato())['por_pagina'] ?? self::POR_PAGINA;

        $prestacoes = $this->afiliado($request)->prestacoes()
            ->latest('periodo_fim')
            ->latest('id')
            ->paginate($por_pagina)
            ->withQueryString();

        return AfiliadosPrestacoesContasResource::collection($prestacoes);
    }

    private function afiliado(Request $request): Afiliados
    {
        return $request->user('afiliados');
    }
}
