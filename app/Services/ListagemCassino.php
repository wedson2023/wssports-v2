<?php

namespace App\Services;

use App\Enums\AparelhoCassino;
use App\Http\Resources\JogoCassinoResource;
use App\Models\CassinosJogos;
use App\Models\CassinosProvedores;
use App\Models\Configuracoes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Jogos do cassino para o site (spec 011, R-07): tela inicial (provedores, tipos e linhas) em cache
 * por aparelho e a grade filtrada e paginada.
 */
class ListagemCassino
{
    /**
     * Jogos por linha da tela inicial (como o antigo).
     */
    private const JOGOS_POR_LINHA = 10;

    private const CHAVE_CACHE = 'cassino.inicio.';

    /**
     * Tela inicial: provedores e tipos com a quantidade de jogos visíveis, a linha de Populares e uma
     * linha por tipo.
     *
     * @return array{provedores: list<array<string, mixed>>, tipos: list<array<string, mixed>>, linhas: list<array<string, mixed>>, demo_habilitado: bool}
     */
    public function inicio(AparelhoCassino $aparelho): array
    {
        return Cache::remember(
            self::CHAVE_CACHE.$aparelho->value,
            now()->addMinutes((int) config('cassino.minutos_cache_inicio')),
            fn () => $this->montar_inicio($aparelho),
        );
    }

    /**
     * Grade filtrada: a busca pelo nome ignora provedor e tipo, como no antigo.
     *
     * @param  array{provedor?: ?int, tipo?: ?string, busca?: ?string, por_pagina?: ?int, pagina?: ?int}  $filtros
     */
    public function jogos(AparelhoCassino $aparelho, array $filtros): LengthAwarePaginator
    {
        $consulta = $this->consulta($aparelho);

        if (! empty($filtros['busca'])) {
            $consulta->where('cassinos_jogos.nome', 'like', '%'.addcslashes($filtros['busca'], '%_\\').'%');
        } else {
            $consulta
                ->when(! empty($filtros['provedor']), fn (Builder $c) => $c->where('cassinos_provedores_id', $filtros['provedor']))
                ->when(($filtros['tipo'] ?? null) === 'populares', fn (Builder $c) => $c->where(fn (Builder $p) => $p->where('popular', true)->orWhere('aberturas', '>', 0)))
                ->when(! empty($filtros['tipo']) && $filtros['tipo'] !== 'populares', fn (Builder $c) => $c->where('tipo', $filtros['tipo']));
        }

        $pagina = $consulta
            ->orderByDesc('popular')
            ->orderBy('ordem')
            ->orderByDesc('aberturas')
            ->orderBy('cassinos_jogos.nome')
            ->paginate(min((int) ($filtros['por_pagina'] ?? 50), 100), ['cassinos_jogos.*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        $this->marcar_demo($pagina->getCollection());

        return $pagina;
    }

    /**
     * Linha Populares: os marcados como populares pela ordem e, até completar o limite, os mais
     * abertos.
     *
     * @return Collection<int, CassinosJogos>
     */
    public function populares(AparelhoCassino $aparelho, int $limite = self::JOGOS_POR_LINHA): Collection
    {
        $marcados = $this->consulta($aparelho)->where('popular', true)->orderBy('ordem')->limit($limite)->get();

        $mais_abertos = $marcados->count() < $limite
            ? $this->consulta($aparelho)
                ->whereNotIn('cassinos_jogos.id', $marcados->pluck('id'))
                ->orderByDesc('aberturas')
                ->orderBy('ordem')
                ->orderBy('cassinos_jogos.id')
                ->limit($limite - $marcados->count())
                ->get()
            : new Collection;

        return $this->marcar_demo($marcados->concat($mais_abertos));
    }

    /**
     * Apaga a tela inicial em cache (sincronização, painel e configuração).
     */
    public static function limpar_cache(): void
    {
        foreach (AparelhoCassino::cases() as $aparelho) {
            Cache::forget(self::CHAVE_CACHE.$aparelho->value);
        }
    }

    /**
     * @return array{provedores: list<array<string, mixed>>, tipos: list<array<string, mixed>>, linhas: list<array<string, mixed>>, demo_habilitado: bool}
     */
    private function montar_inicio(AparelhoCassino $aparelho): array
    {
        $provedores = CassinosProvedores::ativos()
            ->withCount(['jogos as quantidade' => fn (Builder $jogo) => $jogo->visiveis($aparelho)])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get()
            ->filter(fn (CassinosProvedores $provedor) => $provedor->quantidade > 0)
            ->map(fn (CassinosProvedores $provedor) => [
                'id' => $provedor->id,
                'codigo' => $provedor->codigo,
                'nome' => $provedor->nome,
                'imagem' => $provedor->url_imagem(),
                'quantidade' => (int) $provedor->quantidade,
            ])
            ->values()
            ->all();

        $tipos = CassinosJogos::visiveis($aparelho)
            ->selectRaw('tipo, count(*) as quantidade')
            ->groupBy('tipo')
            ->orderByDesc('quantidade')
            ->get()
            ->map(fn (CassinosJogos $linha) => ['tipo' => $linha->tipo, 'quantidade' => (int) $linha->quantidade])
            ->all();

        $linhas = [['tipo' => 'populares', 'jogos' => $this->serializar($this->populares($aparelho))]];

        // uma linha por tipo, sem os jogos sem tipo (como o antigo, que ignorava tipo vazio)
        foreach ($tipos as ['tipo' => $tipo]) {
            if ($tipo === 'outros') {
                continue;
            }

            $jogos = $this->consulta($aparelho)
                ->where('tipo', $tipo)
                ->orderByDesc('popular')
                ->orderBy('ordem')
                ->orderByDesc('aberturas')
                ->orderBy('cassinos_jogos.id')
                ->limit(self::JOGOS_POR_LINHA)
                ->get();

            $linhas[] = ['tipo' => $tipo, 'jogos' => $this->serializar($this->marcar_demo($jogos))];
        }

        return [
            'provedores' => $provedores,
            'tipos' => $tipos,
            'linhas' => $linhas,
            'demo_habilitado' => (bool) Configuracoes::atual()->cassino_demo_habilitado,
        ];
    }

    /**
     * Jogos visíveis com o provedor e os canais abríveis carregados (sem consulta por jogo).
     */
    private function consulta(AparelhoCassino $aparelho): Builder
    {
        return CassinosJogos::visiveis($aparelho)->with([
            'provedor:id,nome',
            'canais' => fn ($canal) => $canal->abriveis()->select('id', 'cassinos_jogos_id', 'demo'),
        ]);
    }

    /**
     * Marca em cada jogo se o botão Demo aparece: demo liberado no hall e algum canal abrível com demo.
     *
     * @param  Collection<int, CassinosJogos>  $jogos
     * @return Collection<int, CassinosJogos>
     */
    private function marcar_demo(Collection $jogos): Collection
    {
        $demo_habilitado = (bool) Configuracoes::atual()->cassino_demo_habilitado;

        return $jogos->each(fn (CassinosJogos $jogo) => $jogo->setAttribute('tem_demo', $demo_habilitado && $jogo->canais->contains('demo', true)));
    }

    /**
     * @param  Collection<int, CassinosJogos>  $jogos
     * @return list<array<string, mixed>>
     */
    private function serializar(Collection $jogos): array
    {
        return JogoCassinoResource::collection($jogos)->resolve();
    }
}
