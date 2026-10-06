<?php

namespace App\Services;

use App\Enums\AlvoRegra;
use App\Enums\SituacaoAoVivo;
use App\Enums\SituacaoConfronto;
use App\Models\ClientesConfiguracoes;
use App\Models\Configuracoes;
use App\Models\UsuariosConfiguracoes;
use App\Models\VisitantesConfiguracoes;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Listagem pública de jogos do pré-jogo e do ao vivo, para o público identificado. Os filtros de
 * dia são calculados no fuso pedido e convertidos para UTC antes da consulta; o fuso nunca entra
 * no SQL.
 */
class ListagemConfrontos
{
    private const FUSO_PADRAO = '-03:00';

    private const POR_PAGINA_PADRAO = 50;

    private const ESPORTE_PADRAO = 'FUTEBOL';

    public function __construct(private CalculoCotacoes $calculo) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function pre_jogo(Publico $publico, array $filtros): array
    {
        $fuso = new CarbonTimeZone($filtros['fuso_horario'] ?? self::FUSO_PADRAO);
        [$inicio, $fim] = $this->janela($filtros, $fuso);

        $consulta = DB::table('confrontos as co')
            ->join('campeonatos as ca', 'ca.id', '=', 'co.campeonatos_id')
            ->whereNull('co.deleted_at')
            ->whereNull('ca.deleted_at')
            ->where('co.ativo', true)
            ->where('ca.ativo', true)
            ->where('co.situacao', SituacaoConfronto::Aguardando->value)
            ->whereBetween('co.data_inicio', [$inicio, $fim]);

        $this->filtros_comuns($consulta, $publico, $filtros);
        $this->excluir_nao_permitidos($consulta, $publico, 'campeonatos_nao_permitidos', 'campeonatos_id', 'co.campeonatos_id');
        $this->excluir_nao_permitidos($consulta, $publico, 'confrontos_nao_permitidos', 'confrontos_id', 'co.id');

        $pagina = (clone $consulta)
            ->select([
                'co.id', 'co.id as confrontos_id', 'co.campeonatos_id', 'co.time_casa', 'co.escudo_casa', 'co.time_fora',
                'co.escudo_fora', 'co.esporte', 'co.data_inicio', 'co.cotacoes', 'co.quantidade_cotacoes',
            ])
            ->addSelect(['ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira'])
            ->orderByDesc('ca.favorito')
            ->orderBy('ca.nome')
            ->orderBy('co.data_inicio')
            ->orderBy('co.time_casa')
            ->paginate($filtros['por_pagina'] ?? self::POR_PAGINA_PADRAO, ['*'], 'pagina', $filtros['pagina'] ?? 1);

        $cotacoes = $this->calculo->ajustar($publico, CalculoCotacoes::PRE_JOGO, $this->decodificar($pagina->getCollection()));

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
        $configuracoes = Configuracoes::atual();
        $configuracao_vendedor = $publico->e_vendedor() ? UsuariosConfiguracoes::do_vendedor($publico->usuario->id) : null;

        $this->garantir_ao_vivo_habilitado($publico, $configuracoes, $configuracao_vendedor);

        $fuso = new CarbonTimeZone($filtros['fuso_horario'] ?? self::FUSO_PADRAO);
        $minuto_limite = $configuracao_vendedor?->minuto_limite_ao_vivo ?? $configuracoes->minuto_limite_ao_vivo;

        $consulta = DB::table('confrontos_ao_vivo as av')
            // o ao vivo só exibe jogos que a banca tem na grade, com o confronto do pré-jogo ativo
            ->join('confrontos as co', 'co.id', '=', 'av.confrontos_id')
            ->join('campeonatos as ca', 'ca.id', '=', 'av.campeonatos_id')
            ->whereNull('av.deleted_at')
            ->whereNull('co.deleted_at')
            ->whereNull('ca.deleted_at')
            ->where('co.ativo', true)
            ->where('ca.ativo', true)
            ->whereIn('av.situacao', array_column(SituacaoAoVivo::cases(), 'value'))
            ->where('av.minuto', '<=', $minuto_limite)
            ->where('av.ultima_atualizacao_em', '>=', now()->subMinutes($configuracoes->minutos_permanencia_ao_vivo));

        $this->filtros_comuns($consulta, $publico, $filtros, 'av');
        $this->excluir_nao_permitidos($consulta, $publico, 'campeonatos_nao_permitidos', 'campeonatos_id', 'av.campeonatos_id');
        $this->excluir_nao_permitidos($consulta, $publico, 'confrontos_ao_vivo_nao_permitidos', 'confrontos_id', 'av.confrontos_id');

        $pagina = (clone $consulta)
            ->select([
                'av.id', 'av.confrontos_id', 'av.campeonatos_id', 'av.time_casa', 'av.escudo_casa', 'av.time_fora',
                'av.escudo_fora', 'av.esporte', 'av.data_inicio', 'av.cotacoes', 'av.quantidade_cotacoes', 'av.placar_casa',
                'av.placar_fora', 'av.minuto', 'av.cronometro', 'av.situacao', 'av.ultima_atualizacao_em',
            ])
            ->addSelect(['ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira'])
            ->orderByDesc('ca.favorito')
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
        $permitidos = $this->esportes_permitidos($publico);

        $consulta->where("{$tabela}.esporte", $esporte);

        if ($permitidos !== null && ! in_array($esporte, array_map('mb_strtoupper', $permitidos), true)) {
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
     * Esportes que o público pode ver; null = todos (gestores).
     *
     * @return list<string>|null
     */
    private function esportes_permitidos(Publico $publico): ?array
    {
        return match (true) {
            $publico->e_visitante() => VisitantesConfiguracoes::atual()->esportes_visiveis(),
            $publico->e_cliente() => $this->esportes_do_cliente($publico),
            $publico->e_vendedor() => UsuariosConfiguracoes::do_vendedor($publico->usuario->id)->esportes_visiveis(),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function esportes_do_cliente(Publico $publico): array
    {
        $configuracao = ClientesConfiguracoes::where('clientes_id', $publico->cliente->id)->first();

        if ($configuracao === null) {
            return [self::ESPORTE_PADRAO];
        }

        return $configuracao->apostar_outros_esportes ? array_values($configuracao->esportes_permitidos ?? []) : [self::ESPORTE_PADRAO];
    }

    /**
     * Tira da consulta os itens não permitidos para o público: alvo Todos; alvo Clientes sem
     * cliente indicado (site) ou indicado para o cliente logado; alvo Vendedores com dono na
     * cadeia do usuário do painel.
     */
    private function excluir_nao_permitidos(Builder $consulta, Publico $publico, string $tabela, string $coluna, string $referencia): void
    {
        $consulta->whereNotExists(function (Builder $restricao) use ($publico, $tabela, $coluna, $referencia) {
            $restricao->selectRaw('1')
                ->from("{$tabela} as np")
                ->whereColumn("np.{$coluna}", $referencia)
                ->whereNull('np.deleted_at')
                ->where(function (Builder $alvos) use ($publico) {
                    $alvos->where('np.alvo', AlvoRegra::Todos->value);

                    if ($publico->e_site()) {
                        $alvos->orWhere(fn (Builder $c) => $c->where('np.alvo', AlvoRegra::Clientes->value)
                            ->where(fn (Builder $cliente) => $cliente->whereNull('np.clientes_id')
                                ->when($publico->e_cliente(), fn (Builder $x) => $x->orWhere('np.clientes_id', $publico->cliente->id))));
                    } else {
                        $alvos->orWhere(fn (Builder $c) => $c->where('np.alvo', AlvoRegra::Vendedores->value)
                            ->whereIn('np.usuarios_id', $publico->ids_hierarquia_acima()));
                    }
                });
        });
    }

    private function garantir_ao_vivo_habilitado(Publico $publico, Configuracoes $configuracoes, ?UsuariosConfiguracoes $configuracao_vendedor): void
    {
        $habilitado = $configuracoes->ao_vivo_habilitado
            && (! $publico->e_visitante() || VisitantesConfiguracoes::atual()->ao_vivo_habilitado)
            && ($configuracao_vendedor === null || $configuracao_vendedor->ao_vivo_habilitado);

        if (! $habilitado) {
            throw new HttpException(403, 'O ao vivo não está disponível.');
        }
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
