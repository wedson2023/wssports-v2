<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirAlcanceAfiliado;
use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Resources\AfiliadosLancamentosResource;
use App\Http\Resources\AfiliadosResource;
use App\Http\Resources\ClienteDoAfiliadoResource;
use App\Models\Afiliados;
use App\Services\ListagemAfiliados;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Extrato de lançamentos e clientes do afiliado na gestão do painel (spec 012, FR-045).
 */
class AfiliadosExtratoController extends Controller implements HasMiddleware
{
    use GarantirAlcanceAfiliado, GarantirPermissaoCliente;

    public function __construct(private ListagemAfiliados $listagem) {}

    public static function middleware(): array
    {
        return [self::permissao_cliente('afiliados.consultar')];
    }

    public function lancamentos(Request $request, Afiliados $afiliado): AnonymousResourceCollection
    {
        $this->garantir_alcance($request, $afiliado);

        $filtros = $request->validate(
            [...ListagemAfiliados::regras_extrato(), 'clientes_id' => ['nullable', 'integer']],
            [...ListagemAfiliados::mensagens_extrato(), 'clientes_id.integer' => 'O cliente informado é inválido.'],
        );

        $lancamentos = $this->listagem
            ->filtrar_lancamentos($afiliado->lancamentos()->with('cliente'), $filtros)
            ->paginate($filtros['por_pagina'] ?? 30)
            ->withQueryString();

        // soma de todas as páginas do filtro, para a gaveta e a prestação (spec 022, R-10)
        $soma = $this->listagem->filtrar_lancamentos($afiliado->lancamentos(), $filtros)->reorder()->sum('valor');

        return AfiliadosLancamentosResource::collection($lancamentos)
            ->additional(['meta' => ['soma' => AfiliadosResource::valor($soma)]]);
    }

    /**
     * Clientes ligados ao afiliado, com a máscara do painel e o total depositado.
     */
    public function clientes(Request $request, Afiliados $afiliado): AnonymousResourceCollection
    {
        $this->garantir_alcance($request, $afiliado);

        $por_pagina = $request->validate(
            ['por_pagina' => ['nullable', 'integer', 'between:1,100']],
            ListagemAfiliados::mensagens_extrato(),
        )['por_pagina'] ?? 20;

        $clientes = $afiliado->clientes()
            ->with(['configuracoes', 'afiliado'])
            ->select('clientes.*')
            ->selectSub(ListagemAfiliados::subconsulta_total_depositado(), 'total_depositado')
            ->orderBy('clientes.nome')
            ->orderBy('clientes.id')
            ->paginate($por_pagina)
            ->withQueryString();

        return ClienteDoAfiliadoResource::collection($clientes);
    }
}
