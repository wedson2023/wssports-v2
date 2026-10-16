<?php

namespace App\Services;

use App\Enums\EfeitoAcaoCassino;
use App\Models\Configuracoes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Relatório do movimento do cassino (spec 011, FR-032, R-22): rodadas, entradas, saídas, GGR e
 * comissão pela porcentagem de cada provedor, por jogo, provedor ou canal. Somas pelo query builder
 * (sem SQL concatenado); GGR e comissão em bcmath.
 */
class RelatorioCassino
{
    /**
     * Colunas agrupadas de cada agrupamento (o jogo fora do catálogo agrupa pelo código do provedor).
     */
    private const AGRUPAMENTOS = [
        'jogo' => ['s.cassinos_jogos_id', 's.codigo_provedor', 'j.nome', 'j.tipo', 'p.id', 'p.nome', 'p.porcentagem_comissao'],
        'provedor' => ['p.id', 'p.nome', 'p.porcentagem_comissao'],
        'canal' => ['c.canal', 'p.id', 'p.nome', 'p.porcentagem_comissao'],
    ];

    /**
     * Mesmas colunas, com nomes sem conflito no resultado.
     */
    private const COLUNAS = [
        'jogo' => ['s.codigo_provedor', 'j.nome as nome_jogo', 'j.tipo', 'p.nome as nome_provedor', 'p.porcentagem_comissao'],
        'provedor' => ['p.nome as nome_provedor', 'p.porcentagem_comissao'],
        'canal' => ['c.canal', 'p.nome as nome_provedor', 'p.porcentagem_comissao'],
    ];

    /**
     * @param  array{data_inicial: string, data_final: string, agrupar?: ?string, provedor?: ?int, pagina?: ?int, por_pagina?: ?int}  $filtros
     * @return array{resumo: array<string, mixed>, itens: LengthAwarePaginator}
     */
    public function gerar(array $filtros): array
    {
        $agrupar = $filtros['agrupar'] ?? 'jogo';
        $inicio = $filtros['data_inicial'].' 00:00:00';
        $fim = $filtros['data_final'].' 23:59:59';

        $itens = $this->somas($this->base($inicio, $fim, $filtros['provedor'] ?? null))
            ->addSelect(self::COLUNAS[$agrupar])
            ->groupBy(self::AGRUPAMENTOS[$agrupar])
            ->orderByDesc('entradas')
            ->paginate(min((int) ($filtros['por_pagina'] ?? 20), 100), ['*'], 'pagina', $filtros['pagina'] ?? null)
            ->withQueryString();

        $itens->setCollection($itens->getCollection()->map(fn (object $linha) => $this->item($linha, $agrupar)));

        return [
            'resumo' => $this->resumo($inicio, $fim, $filtros['provedor'] ?? null),
            'itens' => $itens,
        ];
    }

    /**
     * Totais do período (a comissão soma a de cada provedor) e o GGR desde a última prestação de
     * contas.
     *
     * @return array<string, mixed>
     */
    private function resumo(string $inicio, string $fim, ?int $provedor): array
    {
        $por_provedor = $this->somas($this->base($inicio, $fim, $provedor))
            ->addSelect(self::COLUNAS['provedor'])
            ->groupBy(self::AGRUPAMENTOS['provedor'])
            ->get();

        $resumo = ['rodadas' => 0, 'entradas' => '0.00', 'saidas' => '0.00', 'ggr' => '0.00', 'comissao' => '0.00'];

        foreach ($por_provedor as $linha) {
            $item = $this->item($linha, 'provedor');
            $resumo['rodadas'] += $item['rodadas'];
            $resumo['entradas'] = bcadd($resumo['entradas'], $item['entradas'], 2);
            $resumo['saidas'] = bcadd($resumo['saidas'], $item['saidas'], 2);
            $resumo['ggr'] = bcadd($resumo['ggr'], $item['ggr'], 2);
            $resumo['comissao'] = bcadd($resumo['comissao'], $item['comissao'], 2);
        }

        $prestacao = Configuracoes::atual()->data_prestacao_contas_cassino;
        $desde = $prestacao
            ? $this->somas($this->base($prestacao->toDateString().' 00:00:00', now()->toDateTimeString(), $provedor))->first()
            : null;

        return [
            ...$resumo,
            'ggr_desde_prestacao' => $desde ? bcsub((string) ($desde->entradas ?? '0'), (string) ($desde->saidas ?? '0'), 2) : null,
            'data_prestacao_contas' => $prestacao?->toDateString(),
        ];
    }

    private function base(string $inicio, string $fim, ?int $provedor): Builder
    {
        return DB::table('cassinos_rodadas as r')
            ->join('cassinos_sessoes as s', 's.id', '=', 'r.cassinos_sessoes_id')
            ->leftJoin('cassinos_jogos as j', 'j.id', '=', 's.cassinos_jogos_id')
            ->leftJoin('cassinos_jogos_canais as c', 'c.id', '=', 's.cassinos_jogos_canais_id')
            ->leftJoin('cassinos_provedores as p', 'p.id', '=', 'j.cassinos_provedores_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.created_at', [$inicio, $fim])
            ->when($provedor, fn (Builder $c) => $c->where('p.id', $provedor));
    }

    /**
     * Entradas = apostas − devoluções (cancelamento e reembolso); saídas = prêmios; rodadas = giros
     * distintos (pela identificação da rodada no jogo; a PG Soft manda aposta e prêmio da mesma rodada
     * em dois lançamentos), sem as devoluções.
     */
    private function somas(Builder $consulta): Builder
    {
        $apostas = [EfeitoAcaoCassino::Aposta->value, EfeitoAcaoCassino::ApostaComPrêmio->value];
        $devolucoes = [EfeitoAcaoCassino::Cancelamento->value, EfeitoAcaoCassino::Reembolso->value];

        return $consulta
            ->selectRaw('count(distinct case when r.efeito in (?, ?) then null else coalesce(r.rodada_provedor, r.trade_id) end) as rodadas', $devolucoes)
            ->selectRaw('coalesce(sum(case when r.efeito in (?, ?) then r.valor_aposta else 0 end), 0) - coalesce(sum(case when r.efeito in (?, ?) then r.valor_aposta else 0 end), 0) as entradas', [...$apostas, ...$devolucoes])
            ->selectRaw('coalesce(sum(r.valor_premio), 0) as saidas');
    }

    /**
     * @return array<string, mixed>
     */
    private function item(object $linha, string $agrupar): array
    {
        $entradas = bcadd((string) ($linha->entradas ?? '0'), '0', 2);
        $saidas = bcadd((string) ($linha->saidas ?? '0'), '0', 2);
        $ggr = bcsub($entradas, $saidas, 2);
        $porcentagem = bcadd((string) ($linha->porcentagem_comissao ?? '0'), '0', 2);

        return [
            'grupo' => match ($agrupar) {
                'provedor' => $linha->nome_provedor ?? 'Sem provedor',
                'canal' => $linha->canal ?? 'Sem canal',
                default => $linha->nome_jogo ?? $linha->codigo_provedor ?? 'Jogo fora do catálogo',
            },
            'provedor' => $agrupar === 'provedor' ? null : ($linha->nome_provedor ?? null),
            'canal' => $linha->canal ?? null,
            'tipo' => $linha->tipo ?? null,
            'rodadas' => (int) $linha->rodadas,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'ggr' => $ggr,
            'porcentagem_comissao' => $porcentagem,
            // truncada em centavos (bcmath com escala 2)
            'comissao' => bcdiv(bcmul($ggr, $porcentagem, 4), '100', 2),
        ];
    }
}
