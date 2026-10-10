<?php

namespace App\Services;

use App\Models\Configuracoes;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listagem pública de jogos do pré-jogo e do ao vivo, para o público identificado. O que o
 * público pode ver vem de RegrasExibicao (o mesmo usado na aposta). Os filtros de dia são
 * calculados no fuso pedido e convertidos para UTC antes da consulta; o fuso nunca entra no SQL.
 */
class ListagemConfrontos
{
    private const FUSO_PADRAO = '-03:00';

    private const POR_PAGINA_PADRAO = 50;

    private const ESPORTE_PADRAO = 'FUTEBOL';

    public function __construct(private CalculoCotacoes $calculo, private RegrasExibicao $regras) {}

    /**
     * Filtros opcionais da tabela do vendedor (spec 006, R-08): `campeonatos` (lista de ids, além
     * do dia pedido) e `codigos_cotacao` (códigos calculados no lugar dos 4 da listagem).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function pre_jogo(Publico $publico, array $filtros): array
    {
        $fuso = new CarbonTimeZone($filtros['fuso_horario'] ?? self::FUSO_PADRAO);
        [$inicio, $fim] = $this->janela($filtros, $fuso);

        // jogos visíveis ao público (período, travamento, ao vivo e não permitidos) dentro do dia pedido
        $consulta = $this->regras->consulta_pre_jogo($publico)
            ->whereBetween('co.data_inicio', [$inicio, $fim])
            ->when(
                filled($filtros['campeonatos'] ?? null),
                fn (Builder $filtrada) => $filtrada->whereIn('co.campeonatos_id', array_map('intval', $filtros['campeonatos']))
            );

        $this->filtros_comuns($consulta, $publico, $filtros);

        $pagina = $this->so_do_campeonato(clone $consulta, $filtros, 'co')
            ->select([
                'co.id', 'co.id as confrontos_id', 'co.campeonatos_id', 'co.time_casa', 'co.escudo_casa', 'co.time_fora',
                'co.escudo_fora', 'co.esporte', 'co.data_inicio', 'co.cotacoes', 'co.quantidade_cotacoes',
            ])
            ->addSelect(['ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira'])
            ->orderByDesc('ca.favorito')
            ->orderBy('ca.pais')
            ->orderBy('ca.nome')
            ->orderBy('co.data_inicio')
            ->orderBy('co.time_casa')
            ->paginate($filtros['por_pagina'] ?? self::POR_PAGINA_PADRAO, ['*'], 'pagina', $filtros['pagina'] ?? 1);

        $cotacoes = $this->calculo->ajustar(
            $publico,
            CalculoCotacoes::PRE_JOGO,
            $this->decodificar($pagina->getCollection()),
            $filtros['codigos_cotacao'] ?? CalculoCotacoes::CODIGOS_LISTAGEM,
        );

        $agora = now();

        return $this->resposta($publico, CalculoCotacoes::PRE_JOGO, $pagina, $consulta, fn (object $confronto) => [
            ...$this->dados_confronto($confronto, $fuso),
            'minutos_para_inicio' => (int) $agora->diffInMinutes(Carbon::parse($confronto->data_inicio, 'UTC')),
            'cotacoes' => $cotacoes[$confronto->id],
            'quantidade_cotacoes' => (int) $confronto->quantidade_cotacoes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function ao_vivo(Publico $publico, array $filtros): array
    {
        $this->regras->garantir_ao_vivo_habilitado($publico);

        $configuracoes = Configuracoes::atual();
        $fuso = new CarbonTimeZone($filtros['fuso_horario'] ?? self::FUSO_PADRAO);

        $consulta = $this->regras->consulta_ao_vivo($publico);

        $this->filtros_comuns($consulta, $publico, $filtros, 'av');

        $pagina = $this->so_do_campeonato(clone $consulta, $filtros, 'av')
            ->select([
                'av.id', 'av.confrontos_id', 'av.campeonatos_id', 'av.time_casa', 'av.escudo_casa', 'av.time_fora',
                'av.escudo_fora', 'av.esporte', 'av.data_inicio', 'av.cotacoes', 'av.quantidade_cotacoes', 'av.placar_casa',
                'av.placar_fora', 'av.minuto', 'av.cronometro', 'av.situacao', 'av.ultima_atualizacao_em',
            ])
            ->addSelect(['ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira'])
            ->orderByDesc('ca.favorito')
            ->orderBy('ca.pais')
            ->orderBy('ca.nome')
            ->orderBy('av.data_inicio')
            ->orderBy('av.time_casa')
            ->paginate($filtros['por_pagina'] ?? self::POR_PAGINA_PADRAO, ['*'], 'pagina', $filtros['pagina'] ?? 1);

        $itens = $this->decodificar($pagina->getCollection());
        $cotacoes = $this->calculo->ajustar($publico, CalculoCotacoes::AO_VIVO, $itens);
        $limite_trava = now()->subSeconds($configuracoes->segundos_trava_ao_vivo);

        return $this->resposta($publico, CalculoCotacoes::AO_VIVO, $pagina, $consulta, function (object $jogo) use ($fuso, $cotacoes, $configuracoes, $limite_trava) {
            // travado: trava geral ou sem atualização há mais tempo que o limite; nunca valor antigo
            $travado = $configuracoes->ao_vivo_travado || Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->lt($limite_trava);

            return [
                ...$this->dados_confronto($jogo, $fuso),
                'placar_casa' => (int) $jogo->placar_casa,
                'placar_fora' => (int) $jogo->placar_fora,
                'minuto' => (int) $jogo->minuto,
                'cronometro' => $jogo->cronometro,
                'situacao' => $jogo->situacao,
                'travado' => $travado,
                'cotacoes' => $travado ? array_fill_keys(CalculoCotacoes::CODIGOS_LISTAGEM, 0.0) : $cotacoes[$jogo->id],
                'quantidade_cotacoes' => (int) $jogo->quantidade_cotacoes,
            ];
        });
    }

    /**
     * Início e fim (em UTC) do dia pedido no fuso de quem vê; com busca, de agora até o fim de
     * depois de amanhã. O pré-jogo nunca passa do fim de depois de amanhã.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: Carbon, 1: Carbon}
     */
    private function janela(array $filtros, CarbonTimeZone $fuso): array
    {
        $agora = now();
        $hoje = $agora->copy()->setTimezone($fuso)->startOfDay();

        if (filled($filtros['busca'] ?? null)) {
            return [$agora, $hoje->copy()->addDays(2)->endOfDay()->utc()];
        }

        $deslocamento = match ($filtros['dia'] ?? 'hoje') {
            'amanha' => 1,
            'depois_de_amanha' => 2,
            default => 0,
        };

        $inicio = $hoje->copy()->addDays($deslocamento);
        $fim = $inicio->copy()->endOfDay();

        return [$inicio->utc()->max($agora), $fim->utc()];
    }

    /**
     * Esporte, esportes permitidos ao público, favoritos e busca por time.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function filtros_comuns(Builder $consulta, Publico $publico, array $filtros, string $tabela = 'co'): void
    {
        $esporte = mb_strtoupper($filtros['esporte'] ?? self::ESPORTE_PADRAO);

        $consulta->where("{$tabela}.esporte", $esporte);

        if (! $this->regras->esporte_permitido($publico, $esporte)) {
            // esporte fora dos permitidos ao público: nenhum jogo
            $consulta->whereRaw('1 = 0');
        }

        if (! empty($filtros['somente_favoritos'])) {
            $consulta->where('ca.favorito', true);
        }

        if (filled($filtros['busca'] ?? null)) {
            $termo = '%'.addcslashes($filtros['busca'], '%_\\').'%';

            $consulta->where(fn (Builder $busca) => $busca->where("{$tabela}.time_casa", 'like', $termo)
                ->orWhere("{$tabela}.time_fora", 'like', $termo));
        }
    }

    /**
     * Filtro opcional por campeonato (spec 005, R-25). Vale só para os jogos da página: os países
     * do menu continuam com todos os campeonatos do filtro.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function so_do_campeonato(Builder $consulta, array $filtros, string $tabela): Builder
    {
        return $consulta->when(
            filled($filtros['campeonato'] ?? null),
            fn (Builder $filtrada) => $filtrada->where("{$tabela}.campeonatos_id", (int) $filtros['campeonato'])
        );
    }

    /**
     * Monta a resposta: campeonatos da página com seus confrontos, países com a contagem de todo
     * o filtro e os dados de paginação.
     *
     * @param  callable(object): array<string, mixed>  $formatar
     * @return array<string, mixed>
     */
    private function resposta(Publico $publico, string $tipo, LengthAwarePaginator $pagina, Builder $consulta, callable $formatar): array
    {
        $campeonatos = $pagina->getCollection()
            ->groupBy('campeonatos_id')
            ->map(fn ($confrontos) => [
                'id' => (int) $confrontos->first()->campeonatos_id,
                'nome' => $confrontos->first()->campeonato_nome,
                'pais' => $confrontos->first()->pais,
                'bandeira' => $confrontos->first()->bandeira,
                'confrontos' => $confrontos->map($formatar)->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'token_recusado' => $publico->token_recusado,
            'tipo' => $tipo,
            'total' => $pagina->total(),
            'campeonatos' => $campeonatos,
            'paises' => $this->paises($consulta),
            'meta' => [
                'pagina_atual' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ];
    }

    /**
     * Países com seus campeonatos e a quantidade de jogos, sobre todo o resultado do filtro.
     *
     * @return list<array<string, mixed>>
     */
    private function paises(Builder $consulta): array
    {
        return (clone $consulta)
            ->groupBy('ca.id', 'ca.nome', 'ca.pais', 'ca.bandeira')
            ->orderBy('ca.pais')
            ->orderBy('ca.nome')
            ->get(['ca.id', 'ca.nome', 'ca.pais', 'ca.bandeira', DB::raw('COUNT(*) as quantidade_confrontos')])
            ->groupBy('pais')
            ->map(fn ($campeonatos, $pais) => [
                'pais' => $pais,
                'campeonatos' => $campeonatos->map(fn (object $campeonato) => [
                    'id' => (int) $campeonato->id,
                    'nome' => $campeonato->nome,
                    'bandeira' => $campeonato->bandeira,
                    'quantidade_confrontos' => (int) $campeonato->quantidade_confrontos,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function dados_confronto(object $confronto, CarbonTimeZone $fuso): array
    {
        return [
            'id' => (int) $confronto->id,
            'time_casa' => $confronto->time_casa,
            'escudo_casa' => $confronto->escudo_casa,
            'time_fora' => $confronto->time_fora,
            'escudo_fora' => $confronto->escudo_fora,
            'esporte' => $confronto->esporte,
            'data_inicio' => Carbon::parse($confronto->data_inicio, 'UTC')->setTimezone($fuso)->toIso8601String(),
        ];
    }

    /**
     * Converte a coluna JSON de cotações dos itens da página.
     *
     * @param  Collection<int, object>  $itens
     * @return Collection<int, object>
     */
    private function decodificar($itens)
    {
        return $itens->each(function (object $item) {
            $item->cotacoes = json_decode($item->cotacoes, true) ?? [];
        });
    }
}
