<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\Confrontos;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exposição dos jogos na tela Confrontos (spec 023, R-09 a R-12): ranking das opções e apostas de
 * um jogo, aba Mais apostados e aba Pendentes. Conta só palpites ativos de apostas ativas, no
 * alcance da tela Apostas (ListagemApostas::aplicar_alcance), para os números baterem com ela.
 * Simples = aposta com palpites em um jogo só; múltipla = em mais de um, contada inteira (valor e
 * possível retorno) em cada jogo dela (FR-016).
 */
class ExposicaoConfrontos
{
    // jogo que começou há mais que isso e ainda não tem resultado entra em Pendentes (antigo)
    private const HORAS_ATRASO = 2;

    // e só até esta quantidade de dias depois do início (antigo)
    private const DIAS_PENDENTE = 7;

    public function __construct(
        private readonly ListagemApostas $listagem_apostas,
        private readonly ListagemConfrontosPainel $listagem,
    ) {}

    /**
     * Ranking das opções apostadas no jogo, da mais para a menos apostada (contracts/api §2.4).
     */
    public function ranking(Confrontos $confronto, Usuarios $usuario): Collection
    {
        return $this->palpites_da_rede($usuario)
            ->joinSub($this->jogos_por_aposta([$confronto->id]), 'cont', 'cont.apostas_id', '=', 'apostas.id')
            ->leftJoin('confrontos_jogadores as jog', 'jog.id', '=', 'pal.confrontos_jogadores_id')
            ->where('pal.confrontos_id', $confronto->id)
            ->groupBy('pal.codigo_cotacao', 'pal.confrontos_jogadores_id', 'jog.nome')
            ->select('pal.codigo_cotacao', 'pal.confrontos_jogadores_id as jogador_id', 'jog.nome as jogador_nome')
            ->selectRaw($this->colunas('apostas.id', 'apostas.valor', 'apostas.premio', 'cont.jogos'))
            ->orderByDesc('quantidade')
            ->toBase()
            ->get()
            ->map(fn (object $linha) => [
                'codigo_cotacao' => $linha->codigo_cotacao,
                'jogador' => $linha->jogador_id === null ? null : ['id' => $linha->jogador_id, 'nome' => $linha->jogador_nome],
                ...self::exposicao($linha),
            ]);
    }

    /**
     * Apostas com palpite no jogo, da mais nova para a mais antiga (contracts/api §2.5).
     */
    public function apostas(Confrontos $confronto, Usuarios $usuario, int $por_pagina): LengthAwarePaginator
    {
        return $this->palpites_da_rede($usuario)
            ->joinSub($this->jogos_por_aposta([$confronto->id]), 'cont', 'cont.apostas_id', '=', 'apostas.id')
            ->leftJoin('confrontos_jogadores as jog', 'jog.id', '=', 'pal.confrontos_jogadores_id')
            ->leftJoin('usuarios as ven', 'ven.id', '=', 'apostas.usuarios_id')
            ->leftJoin('clientes as cli', 'cli.id', '=', 'apostas.clientes_id')
            ->where('pal.confrontos_id', $confronto->id)
            ->select(
                'apostas.codigo', 'apostas.created_at', 'apostas.usuarios_id', 'apostas.clientes_id',
                'ven.nome as vendedor_nome', 'cli.nome as cliente_nome', 'cont.jogos',
                'pal.codigo_cotacao', 'pal.confrontos_jogadores_id as jogador_id', 'jog.nome as jogador_nome',
                'apostas.valor', 'apostas.premio', 'apostas.resultado',
            )
            ->orderByDesc('apostas.created_at')
            ->orderByDesc('apostas.id')
            ->toBase()
            ->paginate($por_pagina);
    }

    /**
     * Jogos do filtro com aposta na rede, do mais para o menos apostado (contracts/api §2.2), com
     * os campos da lista (para o mesmo menu da aba Jogos).
     *
     * @param  array<string, mixed>  $filtros  formato de FiltrosConfrontosPainelRequest::filtros()
     */
    public function mais_apostados(Usuarios $usuario, array $filtros, int $por_pagina): LengthAwarePaginator
    {
        $ids = $this->listagem->consulta($filtros)->reorder()->select('confrontos.id')->toBase();

        $por_jogo = $this->palpites_da_rede($usuario)
            ->joinSub($this->jogos_por_aposta($ids), 'cont', 'cont.apostas_id', '=', 'apostas.id')
            ->whereIn('pal.confrontos_id', $ids)
            ->select('pal.confrontos_id', 'apostas.id as apostas_id', 'apostas.valor', 'apostas.premio', 'cont.jogos')
            ->distinct()
            ->toBase();

        $exposicao = DB::query()->fromSub($por_jogo, 'x')
            ->select('x.confrontos_id')
            ->selectRaw($this->colunas('x.apostas_id', 'x.valor', 'x.premio', 'x.jogos'))
            ->groupBy('x.confrontos_id');

        $consulta = $this->listagem->consulta($filtros)
            ->joinSub($exposicao, 'exp', 'exp.confrontos_id', '=', 'confrontos.id')
            ->select('confrontos.*', 'exp.quantidade', 'exp.simples_apostas', 'exp.simples_valor', 'exp.simples_retorno',
                'exp.multiplas_apostas', 'exp.multiplas_valor', 'exp.multiplas_retorno')
            ->reorder()
            ->orderByDesc('exp.quantidade')
            ->orderBy('confrontos.data_inicio');

        return $this->listagem->com_detalhes($consulta, $usuario)->paginate($por_pagina)->withQueryString();
    }

    /**
     * Jogos ativos, ainda Aguardando, que começaram há mais de 2 horas e há menos de 7 dias, com
     * palpites aguardando de apostas ativas (R-12), do maior para o menor atraso.
     */
    public function pendentes(Usuarios $usuario, ?string $busca): Builder
    {
        $agora = now();

        $candidatos = Confrontos::query()
            ->where('ativo', true)
            ->where('situacao', SituacaoConfronto::Aguardando->value)
            ->whereBetween('data_inicio', [$agora->copy()->subDays(self::DIAS_PENDENTE), $agora->copy()->subHours(self::HORAS_ATRASO)])
            ->select('id')
            ->toBase();

        $em_aberto = $this->palpites_da_rede($usuario)
            ->where('pal.resultado', ResultadoAposta::Aguardando->value)
            ->whereIn('pal.confrontos_id', $candidatos)
            ->select('pal.confrontos_id', 'apostas.id as apostas_id', 'apostas.valor')
            ->distinct()
            ->toBase();

        $por_jogo = DB::query()->fromSub($em_aberto, 'x')
            ->select('x.confrontos_id')
            ->selectRaw('COUNT(*) as apostas, SUM(x.valor) as valor_em_aberto')
            ->groupBy('x.confrontos_id');

        return Confrontos::query()
            ->with('campeonato')
            ->joinSub($por_jogo, 'pend', 'pend.confrontos_id', '=', 'confrontos.id')
            ->select('confrontos.*', 'pend.apostas', 'pend.valor_em_aberto')
            ->selectRaw('TIMESTAMPDIFF(MINUTE, confrontos.data_inicio, ?) as atraso_minutos', [$agora->format('Y-m-d H:i:s')])
            ->when($busca, function (Builder $c, string $busca) {
                $termo = '%'.addcslashes($busca, '%_\\').'%';
                $c->where(fn (Builder $b) => $b->where('confrontos.time_casa', 'like', $termo)
                    ->orWhere('confrontos.time_fora', 'like', $termo)
                    ->orWhereHas('campeonato', fn (Builder $ca) => $ca->where('nome', 'like', $termo)));
            })
            ->orderByDesc('atraso_minutos')
            ->orderBy('confrontos.id');
    }

    /**
     * Exposição de uma linha agregada, com os valores em texto de 2 casas.
     *
     * @return array{quantidade: int, simples: array<string, mixed>, multiplas: array<string, mixed>}
     */
    public static function exposicao(object $linha): array
    {
        return [
            'quantidade' => (int) $linha->quantidade,
            'simples' => [
                'apostas' => (int) $linha->simples_apostas,
                'valor' => bcadd((string) ($linha->simples_valor ?? '0'), '0', 2),
                'retorno' => bcadd((string) ($linha->simples_retorno ?? '0'), '0', 2),
            ],
            'multiplas' => [
                'apostas' => (int) $linha->multiplas_apostas,
                'valor' => bcadd((string) ($linha->multiplas_valor ?? '0'), '0', 2),
                'retorno' => bcadd((string) ($linha->multiplas_retorno ?? '0'), '0', 2),
            ],
        ];
    }

    /**
     * Palpites ativos de apostas ativas (alias apostas e pal), no alcance de quem consulta.
     */
    private function palpites_da_rede(Usuarios $usuario): Builder
    {
        $consulta = Apostas::query()
            ->join('apostas_palpites as pal', 'pal.apostas_id', '=', 'apostas.id')
            ->whereNull('pal.deleted_at')
            ->where('apostas.situacao', SituacaoAposta::Ativa->value)
            ->where('pal.situacao', SituacaoPalpite::Ativo->value);

        $this->listagem_apostas->aplicar_alcance($consulta, $usuario, null);

        return $consulta;
    }

    /**
     * Quantidade de jogos distintos com palpite ativo em cada aposta que tem palpite nos jogos
     * informados (lista de ids ou subconsulta de ids).
     *
     * @param  list<int>|QueryBuilder  $confrontos
     */
    private function jogos_por_aposta(array|QueryBuilder $confrontos): QueryBuilder
    {
        $apostas_dos_jogos = DB::table('apostas_palpites')
            ->select('apostas_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoPalpite::Ativo->value)
            ->whereIn('confrontos_id', $confrontos);

        return DB::table('apostas_palpites')
            ->select('apostas_id')
            ->selectRaw('COUNT(DISTINCT confrontos_id) as jogos')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoPalpite::Ativo->value)
            ->whereIn('apostas_id', $apostas_dos_jogos)
            ->groupBy('apostas_id');
    }

    /**
     * Colunas agregadas da exposição: total, simples e múltiplas (quantidade, valor e retorno).
     */
    private function colunas(string $aposta, string $valor, string $premio, string $jogos): string
    {
        return implode(', ', [
            "COUNT(DISTINCT {$aposta}) as quantidade",
            "COUNT(DISTINCT CASE WHEN {$jogos} = 1 THEN {$aposta} END) as simples_apostas",
            "COALESCE(SUM(CASE WHEN {$jogos} = 1 THEN {$valor} END), 0) as simples_valor",
            "COALESCE(SUM(CASE WHEN {$jogos} = 1 THEN {$premio} END), 0) as simples_retorno",
            "COUNT(DISTINCT CASE WHEN {$jogos} > 1 THEN {$aposta} END) as multiplas_apostas",
            "COALESCE(SUM(CASE WHEN {$jogos} > 1 THEN {$valor} END), 0) as multiplas_valor",
            "COALESCE(SUM(CASE WHEN {$jogos} > 1 THEN {$premio} END), 0) as multiplas_retorno",
        ]);
    }
}
