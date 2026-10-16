<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CassinosProvedoresRequest;
use App\Http\Resources\CassinosProvedoresResource;
use App\Models\CassinosProvedores;
use App\Services\ArmazenamentoImagens;
use App\Services\ListagemCassino;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Provedores do cassino no painel (spec 011, FR-030, FR-031): ativo, ordem, nome exibido, logo e
 * porcentagem de comissão do relatório.
 */
class CassinosProvedoresController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_jogos.gerenciar')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $por_pagina = min(max((int) $request->query('por_pagina', 20), 1), 100);

        $provedores = CassinosProvedores::query()
            ->withCount(['jogos as quantidade_jogos' => fn ($jogo) => $jogo->where('ativo', true)->whereNull('removido_em')])
            ->orderByDesc('ativo')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->paginate($por_pagina, ['*'], 'pagina')
            ->withQueryString();

        return CassinosProvedoresResource::collection($provedores);
    }

    public function update(CassinosProvedoresRequest $request, CassinosProvedores $provedor, ArmazenamentoImagens $imagens): CassinosProvedoresResource
    {
        $dados = $request->safe()->except('imagem');

        if ($request->hasFile('imagem')) {
            $anterior = $provedor->imagem;
            $dados['imagem'] = $imagens->salvar($request->file('imagem'), 'cassino/provedores');

            // as logos do sistema antigo (public/images) não são apagadas
            if ($anterior !== null && ! str_starts_with($anterior, '/') && $anterior !== $dados['imagem']) {
                $imagens->remover($anterior);
            }
        }

        $provedor->update($dados);
        ListagemCassino::limpar_cache();

        return new CassinosProvedoresResource($provedor->loadCount(['jogos as quantidade_jogos' => fn ($jogo) => $jogo->where('ativo', true)->whereNull('removido_em')]));
    }
}
