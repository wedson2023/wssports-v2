<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GarantirPermissaoCliente;
use App\Http\Requests\CassinosJogosRequest;
use App\Http\Requests\ListagemCassinosJogosRequest;
use App\Http\Resources\CassinosJogosResource;
use App\Models\CassinosJogos;
use App\Services\ListagemCassino;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Catálogo do cassino no painel (spec 011, FR-030): lista os jogos com os canais, ativa e desativa,
 * marca populares e define a ordem.
 */
class CassinosJogosController extends Controller implements HasMiddleware
{
    use GarantirPermissaoCliente;

    public static function middleware(): array
    {
        return [self::permissao_cliente('cassinos_jogos.gerenciar')];
    }

    public function index(ListagemCassinosJogosRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $jogos = CassinosJogos::query()
            ->with(['provedor', 'canais' => fn ($canal) => $canal->porPrioridade()])
            ->when($filtros['busca'] ?? null, fn (Builder $c, $busca) => $c->where('nome', 'like', '%'.addcslashes($busca, '%_\\').'%'))
            ->when($filtros['provedor'] ?? null, fn (Builder $c, $provedor) => $c->where('cassinos_provedores_id', $provedor))
            ->when($filtros['tipo'] ?? null, fn (Builder $c, $tipo) => $c->where('tipo', $tipo))
            ->when(isset($filtros['ativo']), fn (Builder $c) => $c->where('ativo', (bool) $filtros['ativo']))
            ->when(isset($filtros['popular']), fn (Builder $c) => $c->where('popular', (bool) $filtros['popular']))
            ->when(($filtros['removido'] ?? null) === 'sim', fn (Builder $c) => $c->whereNotNull('removido_em'))
            ->when(($filtros['removido'] ?? null) === 'nao', fn (Builder $c) => $c->whereNull('removido_em'))
            ->when($filtros['sem_canal_abrivel'] ?? false, fn (Builder $c) => $c->whereDoesntHave('canais', fn (Builder $canal) => $canal->abriveis()))
            // o mesmo nome em mais de um provedor aparece duas vezes na página (R-02)
            ->when($filtros['repetidos'] ?? false, fn (Builder $c) => $c->whereIn('nome_normalizado', fn ($repetidos) => $repetidos
                ->select('nome_normalizado')
                ->from('cassinos_jogos')
                ->whereNull('deleted_at')
                ->groupBy('nome_normalizado')
                ->havingRaw('count(distinct cassinos_provedores_id) > 1')))
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate($filtros['por_pagina'] ?? 20, ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        return CassinosJogosResource::collection($jogos);
    }

    public function update(CassinosJogosRequest $request, CassinosJogos $jogo): CassinosJogosResource
    {
        $jogo->update($request->validated());
        ListagemCassino::limpar_cache();

        $jogo->load(['provedor', 'canais' => fn ($canal) => $canal->porPrioridade()]);

        return (new CassinosJogosResource($jogo))->com_aviso($this->aviso_repetido($jogo));
    }

    /**
     * Jogo ativado com o mesmo nome de outro jogo visível de outro provedor.
     */
    private function aviso_repetido(CassinosJogos $jogo): ?string
    {
        if (! $jogo->ativo) {
            return null;
        }

        $repetido = CassinosJogos::where('nome_normalizado', $jogo->nome_normalizado)
            ->where('cassinos_provedores_id', '!=', $jogo->cassinos_provedores_id)
            ->visiveisEmAlgumAparelho()
            ->exists();

        return $repetido ? 'Já existe um jogo com este nome em outro provedor; ele aparecerá duas vezes na página.' : null;
    }
}
