<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoSaque;
use App\Enums\TipoTransacao;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Caixa clientes do painel (spec 017, FR-023 a FR-025, research R-06): no período, as apostas online
 * (as contas da Caixa online, só saldo real), o cassino com saldo real (regras do relatório do
 * cassino), os bônus concedidos e convertidos, depósitos e saques, e o resultado do site; no total e
 * por cliente. Somas no banco; contas em bcmath.
 */
class CaixaClientes
{
    public function __construct(
        private readonly CaixaOnline $caixa_online,
        private readonly RelatorioCassino $cassino,
    ) {}

    /**
     * Totais do período.
     *
     * @return array<string, mixed>
     */
    public function resumo(Carbon $inicio, Carbon $fim): array
    {
        $online = $this->caixa_online->calcular($inicio, $fim);
        $cassino = $this->cassino->somas_periodo($inicio, $fim, Carteira::Saldo->value)['total'];
        $bonus = $this->bonus(null, $inicio, $fim)[0] ?? ['concedidos' => '0.00', 'convertidos' => '0.00'];

        $esportes = array_intersect_key($online, array_flip(['entradas', 'em_aberto', 'premiadas', 'perdidas', 'saidas', 'encerramentos', 'saldo_liquido']));

        return [
            'esportes' => $esportes,
            'cassino' => $cassino,
            'bonus' => $bonus,
            'depositos' => $online['depositos'],
            'saques' => $online['saques_pagos'],
            'resultado_site' => $this->resultado_site($esportes['saldo_liquido'], $cassino, $bonus['convertidos']),
        ];
    }

    /**
     * Clientes com movimento no período, paginados no banco, com as somas só dos da página.
     */
    public function clientes(Carbon $inicio, Carbon $fim, ?string $busca, string $ordenar, string $direcao, int $por_pagina, int $pagina): LengthAwarePaginator
    {
        $com_movimento = DB::query()->fromSub($this->ids_com_movimento($inicio, $fim), 'movimento')->select('clientes_id')->distinct();

        $consulta = DB::table('clientes as c')
            ->whereIn('c.id', $com_movimento)
            ->when($busca, fn (Builder $c) => $c->where(fn (Builder $b) => $b
                ->where('c.nome', 'like', '%'.$busca.'%')
                ->orWhere('c.telefone', 'like', '%'.$busca.'%')
                ->orWhere('c.email', 'like', '%'.$busca.'%')))
            ->select('c.id', 'c.nome', 'c.ddi', 'c.telefone', 'c.email', 'c.deleted_at');

        // ordenar por entradas ou resultado exige as somas de todos os clientes do período
        if (in_array($ordenar, ['entradas', 'resultado'], true)) {
            $todos = $consulta->get();
            $linhas = $this->linhas($todos->all(), $inicio, $fim);
            $campo = $ordenar === 'entradas' ? 'entradas' : 'liquido';

            usort($linhas, fn (array $a, array $b) => bccomp($a[$campo], $b[$campo], 2) ?: $a['id'] <=> $b['id']);
            $linhas = $direcao === 'desc' ? array_reverse($linhas) : $linhas;

            return new LengthAwarePaginator(array_slice($linhas, ($pagina - 1) * $por_pagina, $por_pagina), count($linhas), $por_pagina, $pagina);
        }

        $pagina_clientes = $consulta->orderBy('c.nome', $direcao)->orderBy('c.id')->paginate($por_pagina, ['*'], 'page', $pagina);

        return $pagina_clientes->setCollection(collect($this->linhas($pagina_clientes->items(), $inicio, $fim)));
    }

    /**
     * Somas por cliente dos clientes informados.
     *
     * @param  list<object>  $clientes
     * @return list<array<string, mixed>>
     */
    private function linhas(array $clientes, Carbon $inicio, Carbon $fim): array
    {
        $ids = array_map(fn (object $cliente) => $cliente->id, $clientes);

        if ($ids === []) {
            return [];
        }

        $online = $this->caixa_online->por_cliente($ids, $inicio, $fim);
        $cassino = $this->cassino->somas_periodo($inicio, $fim, Carteira::Saldo->value, $ids)['por_cliente'];
        $bonus = $this->bonus($ids, $inicio, $fim);
        $depositos = $this->por_cliente('depositos', 'pago_em', [SituacaoDeposito::Aprovado->value], $ids, $inicio, $fim);
        $saques = $this->por_cliente('saques', 'pago_em', [SituacaoSaque::Pago->value], $ids, $inicio, $fim);

        return array_map(function (object $cliente) use ($online, $cassino, $bonus, $depositos, $saques) {
            $esportes = $online[$cliente->id];
            $do_cassino = $cassino[$cliente->id] ?? ['entradas' => '0.00', 'saidas' => '0.00', 'resultado' => '0.00', 'comissao_provedores' => '0.00'];
            $convertidos = $bonus[$cliente->id]['convertidos'] ?? '0.00';

            return [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'telefone' => trim($cliente->ddi.' '.$cliente->telefone),
                'email' => $cliente->email,
                'situacao' => $cliente->deleted_at ? 'excluido' : 'ativo',
                'apostas' => (int) $esportes['apostas'],
                'entradas' => $esportes['entradas'],
                'saidas' => $esportes['saidas'],
                'encerramentos' => $esportes['encerramentos'],
                'resultado_cassino' => $do_cassino['resultado'],
                'bonus_convertidos' => $convertidos,
                'depositos' => $depositos[$cliente->id] ?? '0.00',
                'saques' => $saques[$cliente->id] ?? '0.00',
                'liquido' => $this->resultado_site($esportes['saldo_liquido'], $do_cassino, $convertidos),
            ];
        }, $clientes);
    }

    /**
     * Resultado do site = saldo líquido de esportes + resultado do cassino − comissão dos
     * provedores − bônus convertidos.
     *
     * @param  array<string, string>  $cassino
     */
    private function resultado_site(string $saldo_esportes, array $cassino, string $convertidos): string
    {
        $resultado = bcadd($saldo_esportes, $cassino['resultado'], 2);

        return bcsub(bcsub($resultado, $cassino['comissao_provedores'], 2), $convertidos, 2);
    }

    /**
     * Bônus concedidos (promoções creditadas nas carteiras de promoção) e convertidos (passados para
     * o saldo real) no período; sem ids, o total da banca na posição 0.
     *
     * @param  list<int>|null  $ids
     * @return array<int, array{concedidos: string, convertidos: string}>
     */
    private function bonus(?array $ids, Carbon $inicio, Carbon $fim): array
    {
        $carteiras_promocao = [Carteira::PromoçãoEsportes->value, Carteira::PromoçãoCassino->value];

        $consulta = DB::table('clientes_transacoes')
            ->whereNull('deleted_at')
            ->where('tipo', TipoTransacao::Crédito->value)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($ids !== null, fn (Builder $c) => $c->whereIn('clientes_id', $ids)->groupBy('clientes_id')->addSelect('clientes_id'))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN origem = ? AND carteira IN (?, ?) THEN valor ELSE 0 END), 0) as concedidos',
                [OrigemTransacao::Promoção->value, ...$carteiras_promocao],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN origem = ? AND carteira = ? THEN valor ELSE 0 END), 0) as convertidos',
                [OrigemTransacao::ConversãoBônus->value, Carteira::Saldo->value],
            )
            ->get();

        return $consulta->mapWithKeys(fn (object $linha) => [($ids === null ? 0 : $linha->clientes_id) => [
            'concedidos' => bcadd((string) $linha->concedidos, '0', 2),
            'convertidos' => bcadd((string) $linha->convertidos, '0', 2),
        ]])->all();
    }

    /**
     * Soma do valor por cliente numa tabela de movimento (depósitos ou saques).
     *
     * @param  list<string>  $situacoes
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function por_cliente(string $tabela, string $coluna_data, array $situacoes, array $ids, Carbon $inicio, Carbon $fim): array
    {
        return DB::table($tabela)
            ->whereIn('clientes_id', $ids)
            ->whereIn('situacao', $situacoes)
            ->whereNull('deleted_at')
            ->whereBetween($coluna_data, [$inicio, $fim])
            ->groupBy('clientes_id')
            ->select('clientes_id')
            ->selectRaw('COALESCE(SUM(valor), 0) as total')
            ->pluck('total', 'clientes_id')
            ->map(fn ($valor) => bcadd((string) $valor, '0', 2))
            ->all();
    }

    /**
     * Ids dos clientes com algum movimento no período (apostas, encerramentos, rodadas, bônus,
     * depósitos ou saques).
     */
    private function ids_com_movimento(Carbon $inicio, Carbon $fim): Builder
    {
        $periodo = [$inicio, $fim];

        return DB::table('apostas')->select('clientes_id')->whereNotNull('clientes_id')->whereNull('deleted_at')->where('situacao', SituacaoAposta::Ativa->value)->whereBetween('created_at', $periodo)
            ->union(DB::table('apostas_encerramentos')->select('clientes_id')->whereIn('situacao', [SituacaoEncerramento::Registrado->value, SituacaoEncerramento::Creditado->value])->whereNull('deleted_at')->whereBetween('decidido_em', $periodo))
            ->union(DB::table('cassinos_rodadas')->select('clientes_id')->whereNull('deleted_at')->whereBetween('created_at', $periodo))
            ->union(DB::table('clientes_transacoes')->select('clientes_id')->whereIn('origem', [OrigemTransacao::Promoção->value, OrigemTransacao::ConversãoBônus->value])->whereNull('deleted_at')->whereBetween('created_at', $periodo))
            ->union(DB::table('depositos')->select('clientes_id')->where('situacao', SituacaoDeposito::Aprovado->value)->whereNull('deleted_at')->whereBetween('pago_em', $periodo))
            ->union(DB::table('saques')->select('clientes_id')->where('situacao', SituacaoSaque::Pago->value)->whereNull('deleted_at')->whereBetween('pago_em', $periodo));
    }
}
