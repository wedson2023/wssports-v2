<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\EfeitoAcaoCassino;
use App\Enums\FormaPagamento;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoSaque;
use App\Models\Clientes;
use App\Models\Usuarios;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ranking dos clientes da rede (spec 019, FR-027 e FR-028, research R-08). Depósitos, saques pagos e
 * valor apostado são somados e ordenados no banco; o resultado para a banca reaproveita o cálculo do
 * Caixa clientes (spec 017), para os números baterem entre as telas. Só entram clientes com
 * movimento do critério no período; a posição continua entre as páginas.
 */
class RankingClientes
{
    public function __construct(
        private readonly AlcanceClientes $alcance,
        private readonly CaixaClientes $caixa_clientes,
    ) {}

    /**
     * Página do ranking: cada item com posição, dados do cliente e o bloco do critério.
     */
    public function pagina(Usuarios $usuario, string $criterio, Carbon $inicio, Carbon $fim, int $por_pagina, int $pagina): LengthAwarePaginator
    {
        $restringir = $this->alcance->subconsulta_ids($usuario);

        if ($criterio === 'resultado') {
            $caixa = $this->caixa_clientes->clientes($inicio, $fim, null, 'resultado', 'desc', $por_pagina, $pagina, $restringir);
            $valores = collect($caixa->items())->mapWithKeys(fn (array $linha) => [$linha['id'] => ['resultado' => $linha['liquido']]]);

            return $this->montar($caixa, $valores->all(), $criterio);
        }

        $consulta = match ($criterio) {
            'saques' => $this->por_movimento('saques', 'valor', [SituacaoSaque::Pago->value], $inicio, $fim),
            'valor_apostado' => $this->valor_apostado($inicio, $fim),
            default => $this->por_movimento('depositos', 'valor - valor_estornado', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value], $inicio, $fim),
        };

        if ($restringir !== null) {
            $consulta->whereIn('movimento.clientes_id', $restringir);
        }

        $ordenada = $consulta->orderByDesc('total')->orderBy('movimento.clientes_id')->paginate($por_pagina, ['*'], 'page', $pagina);

        $valores = collect($ordenada->items())->mapWithKeys(fn (object $linha) => [$linha->clientes_id => match ($criterio) {
            'valor_apostado' => ['valor_apostado' => [
                'esportes' => self::dinheiro($linha->esportes),
                'cassino' => self::dinheiro($linha->cassino),
                'total' => self::dinheiro($linha->total),
            ]],
            default => [$criterio => ['quantidade' => (int) $linha->quantidade, 'valor' => self::dinheiro($linha->total)]],
        }]);

        return $this->montar($ordenada, $valores->all(), $criterio);
    }

    /**
     * Quantidade e soma por cliente de depósitos ou saques pagos no período (pela data do pagamento).
     *
     * @param  list<string>  $situacoes
     */
    private function por_movimento(string $tabela, string $expressao_valor, array $situacoes, Carbon $inicio, Carbon $fim): Builder
    {
        $movimento = DB::table($tabela)
            ->whereIn('situacao', $situacoes)
            ->whereNull('deleted_at')
            ->whereBetween('pago_em', [$inicio, $fim])
            ->select('clientes_id')
            ->selectRaw("COUNT(*) as quantidade, COALESCE(SUM({$expressao_valor}), 0) as total")
            ->groupBy('clientes_id');

        return DB::query()->fromSub($movimento, 'movimento');
    }

    /**
     * Valor apostado com saldo real no período: apostas de esportes efetivadas pagas com saldo e
     * entradas do cassino na carteira Saldo (apostas menos devoluções, como no relatório do cassino).
     */
    private function valor_apostado(Carbon $inicio, Carbon $fim): Builder
    {
        $apostas = [EfeitoAcaoCassino::Aposta->value, EfeitoAcaoCassino::ApostaComPrêmio->value];
        $devolucoes = [EfeitoAcaoCassino::Cancelamento->value, EfeitoAcaoCassino::Reembolso->value];

        $esportes = DB::table('apostas')
            ->whereNotNull('clientes_id')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoAposta::Ativa->value)
            ->where('forma_pagamento', FormaPagamento::Saldo->value)
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw('clientes_id, valor as esportes, 0 as cassino');

        $cassino = DB::table('cassinos_rodadas')
            ->whereNull('deleted_at')
            ->where('carteira', Carteira::Saldo->value)
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw(
                'clientes_id, 0 as esportes, CASE WHEN efeito IN (?, ?) THEN valor_aposta WHEN efeito IN (?, ?) THEN -valor_aposta ELSE 0 END as cassino',
                [...$apostas, ...$devolucoes],
            );

        $por_cliente = DB::query()
            ->fromSub($esportes->unionAll($cassino), 'entradas')
            ->select('clientes_id')
            ->selectRaw('SUM(esportes) as esportes, SUM(cassino) as cassino, SUM(esportes) + SUM(cassino) as total')
            ->groupBy('clientes_id')
            ->havingRaw('SUM(esportes) + SUM(cassino) > 0');

        return DB::query()->fromSub($por_cliente, 'movimento');
    }

    /**
     * Junta posição, dados do cliente (saldo, saldo líquido, ativo) e o bloco do critério.
     *
     * @param  array<int, array<string, mixed>>  $valores  por id do cliente
     */
    private function montar(LengthAwarePaginator $pagina, array $valores, string $criterio): LengthAwarePaginator
    {
        $ids = array_keys($valores);
        $clientes = Clientes::withTrashed()
            ->whereIn('id', $ids)
            ->select(['id', 'nome', 'saldo', 'ativo', 'deleted_at'])
            ->addSelect(['saldo_liquido' => FiltrosClientes::subconsulta_saldo_liquido()])
            ->get()
            ->keyBy('id');

        $primeira_posicao = ($pagina->currentPage() - 1) * $pagina->perPage() + 1;
        $vazio = ['depositos' => null, 'valor_apostado' => null, 'resultado' => null, 'saques' => null];

        $itens = array_map(function (int $id, int $indice) use ($clientes, $valores, $primeira_posicao, $vazio) {
            $cliente = $clientes->get($id);

            return [
                'posicao' => $primeira_posicao + $indice,
                'id' => $id,
                'nome' => $cliente?->nome,
                'saldo' => $cliente?->saldo,
                'saldo_liquido' => self::dinheiro($cliente?->saldo_liquido ?? 0),
                'ativo' => (bool) $cliente?->ativo,
                'excluido' => $cliente?->trashed() ?? false,
                ...$vazio,
                ...$valores[$id],
            ];
        }, $ids, array_keys($ids));

        return new LengthAwarePaginator($itens, $pagina->total(), $pagina->perPage(), $pagina->currentPage());
    }

    private static function dinheiro(mixed $valor): string
    {
        return bcadd((string) $valor, '0', 2);
    }
}
