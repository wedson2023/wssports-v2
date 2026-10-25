<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\ModalidadePromocao;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoBonus;
use App\Enums\TipoEstornoBonus;
use App\Http\Requests\BonusRecebidosFiltrosRequest;
use App\Models\ClientesTransacoes;
use App\Models\Usuarios;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aba Bônus recebidos da tela Bônus (spec 020, FR-020 a FR-025, research R-02, R-03 e R-09): cada
 * crédito de promoção dado a um cliente da rede, com o rollover e o estorno dele, a situação calculada
 * no banco, os cartões e o detalhe. Mostra todos os bônus dos clientes da rede, mesmo de promoções que
 * o usuário não vê na aba Promoções (FR-002).
 */
class ListagemBonusRecebidos
{
    private const NAO_ENCONTRADO = 'Bônus não encontrado.';

    public function __construct(private readonly AlcanceClientes $alcance) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(Usuarios $usuario, array $filtros): Builder
    {
        [$situacao_sql, $situacao_valores] = self::situacao_sql();
        $situacao = $filtros['situacao'] ?? SituacaoBonus::EmAndamento->value;
        $em_andamento = $situacao === SituacaoBonus::EmAndamento->value;

        $consulta = $this->base($usuario, $filtros)
            ->leftJoin('afiliados', 'afiliados.id', '=', 'clientes_promocoes.afiliados_id')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'clientes_promocoes_estornos.usuarios_id')
            ->select([
                'clientes_transacoes.id',
                'clientes_transacoes.clientes_id',
                'clientes_transacoes.carteira',
                'clientes_transacoes.valor',
                'clientes_transacoes.created_at as recebido_em',
                'clientes_transacoes.referencia_id as clientes_promocoes_id',
                'clientes.nome as cliente_nome',
                'clientes.deleted_at as cliente_excluido_em',
                'clientes_promocoes.nome as promocao_nome',
                'afiliados.codigo as afiliado_codigo',
                'clientes_rollovers.id as rollover_id',
                'clientes_rollovers.vezes',
                'clientes_rollovers.valor_exigido',
                'clientes_rollovers.valor_apostado',
                'clientes_rollovers.valor_minimo_aposta',
                'clientes_rollovers.valor_maximo_aposta',
                'clientes_rollovers.odd_minima_aposta_simples',
                'clientes_rollovers.odd_minima_aposta_multipla',
                'clientes_rollovers.expira_em',
                'clientes_rollovers.cumprido_em',
                'clientes_rollovers.convertido_em',
                'clientes_rollovers.expirado_em',
                'clientes_rollovers.excluido_em',
                'clientes_rollovers.cancelado_em',
                'clientes_promocoes_estornos.tipo as estorno_tipo',
                'clientes_promocoes_estornos.motivo as estorno_motivo',
                'clientes_promocoes_estornos.usuarios_id as estorno_usuarios_id',
                'usuarios.nome as estorno_autor_nome',
                'clientes_promocoes_estornos.valor_retirado as estorno_valor_retirado',
                'clientes_promocoes_estornos.debito_clientes_transacoes_id as estorno_debito_id',
                'clientes_promocoes_estornos.created_at as estornado_em',
            ])
            ->selectRaw('CASE WHEN clientes_transacoes.carteira = ? THEN clientes.saldo_promocao_esportes ELSE clientes.saldo_promocao_cassino END as saldo_promocional', [Carteira::PromoçãoEsportes->value])
            ->selectRaw('GREATEST(clientes_rollovers.valor_exigido - clientes_rollovers.valor_apostado, 0) as falta')
            ->selectRaw("{$situacao_sql} as situacao", $situacao_valores)
            ->when($situacao !== BonusRecebidosFiltrosRequest::TODOS, fn (Builder $c) => $c->whereRaw("{$situacao_sql} = ?", [...$situacao_valores, $situacao]))
            ->when(isset($filtros['data_inicial'], $filtros['data_final']), fn (Builder $c) => $c->whereBetween('clientes_transacoes.created_at', $this->periodo($filtros)));

        // em andamento: o que expira primeiro antes (sem prazo por último); as demais, os mais novos antes
        if ($em_andamento) {
            return $consulta->orderByRaw('clientes_rollovers.expira_em IS NULL')
                ->orderBy('clientes_rollovers.expira_em')
                ->orderBy('clientes_transacoes.id');
        }

        return $consulta->orderByDesc('clientes_transacoes.created_at')->orderByDesc('clientes_transacoes.id');
    }

    /**
     * Cartões da aba (FR-024): em andamento de todos os tempos e, no período (ou desde sempre),
     * creditado, convertido e retirado; com a busca, a promoção e a modalidade.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, int|string>>
     */
    public function resumo(Usuarios $usuario, array $filtros): array
    {
        [$situacao_sql, $situacao_valores] = self::situacao_sql();
        $periodo = isset($filtros['data_inicial'], $filtros['data_final']) ? $this->periodo($filtros) : null;

        $em_andamento = $this->base($usuario, $filtros)
            ->whereRaw("{$situacao_sql} = ?", [...$situacao_valores, SituacaoBonus::EmAndamento->value])
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(GREATEST(clientes_rollovers.valor_exigido - clientes_rollovers.valor_apostado, 0)), 0) as valor')
            ->first();

        $creditado = $this->base($usuario, $filtros)
            ->when($periodo !== null, fn (Builder $c) => $c->whereBetween('clientes_transacoes.created_at', $periodo))
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(clientes_transacoes.valor), 0) as valor')
            ->first();

        // conversão: o crédito no saldo real é o que passou para o cliente; a modalidade vem da promoção
        $convertido = $this->movimentos($usuario, $filtros, $periodo)
            ->where('clientes_transacoes.origem', OrigemTransacao::ConversãoBônus->value)
            ->where('clientes_transacoes.carteira', Carteira::Saldo->value)
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(clientes_transacoes.valor), 0) as valor')
            ->first();

        // retirado: expirados e excluídos (origens só de bônus, que incluem os cancelamentos automáticos)
        // mais os estornos do painel, pela tabela de estornos (a origem Estorno também é de apostas)
        $sem_estorno = $this->movimentos($usuario, $filtros, $periodo)
            ->whereIn('clientes_transacoes.origem', [OrigemTransacao::BônusExpirado->value, OrigemTransacao::BônusExcluído->value])
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(clientes_transacoes.valor), 0) as valor')
            ->first();

        $estornos = $this->base($usuario, $filtros)
            ->whereNotNull('clientes_promocoes_estornos.id')
            ->where('clientes_promocoes_estornos.tipo', '!=', TipoEstornoBonus::Automático->value)
            ->when($periodo !== null, fn (Builder $c) => $c->whereBetween('clientes_promocoes_estornos.created_at', $periodo))
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(clientes_promocoes_estornos.valor_retirado), 0) as valor')
            ->first();

        return [
            'em_andamento' => ['quantidade' => (int) $em_andamento->quantidade, 'falta' => bcadd((string) $em_andamento->valor, '0', 2)],
            'creditado' => self::total($creditado),
            'convertido' => self::total($convertido),
            'retirado' => [
                'quantidade' => (int) $sem_estorno->quantidade + (int) $estornos->quantidade,
                'valor' => bcadd((string) $sem_estorno->valor, (string) $estornos->valor, 2),
            ],
        ];
    }

    /**
     * Um bônus da rede (crédito de promoção), com as regras e o histórico; fora disso, 404.
     */
    public function detalhe(Usuarios $usuario, int $id): object
    {
        $bonus = $this->consulta($usuario, ['situacao' => BonusRecebidosFiltrosRequest::TODOS])
            ->where('clientes_transacoes.id', $id)
            ->first();

        abort_if($bonus === null, 404, self::NAO_ENCONTRADO);

        return $bonus;
    }

    /**
     * Crédito de promoção da rede, para o estorno individual; fora disso, 404.
     */
    public function credito(Usuarios $usuario, int $id): ClientesTransacoes
    {
        $credito = ClientesTransacoes::where('origem', OrigemTransacao::Promoção)->find($id);

        abort_if($credito === null || ! $this->alcance->contem($usuario, $credito->clientes_id), 404, self::NAO_ENCONTRADO);

        return $credito;
    }

    /**
     * Créditos de promoção dos clientes da rede, com cliente, promoção, rollover e estorno, e a busca,
     * a promoção e a modalidade.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function base(Usuarios $usuario, array $filtros): Builder
    {
        $consulta = DB::table('clientes_transacoes')
            ->join('clientes', 'clientes.id', '=', 'clientes_transacoes.clientes_id')
            ->join('clientes_promocoes', 'clientes_promocoes.id', '=', 'clientes_transacoes.referencia_id')
            ->leftJoin('clientes_rollovers', fn ($join) => $join->on('clientes_rollovers.clientes_transacoes_id', '=', 'clientes_transacoes.id')->whereNull('clientes_rollovers.deleted_at'))
            ->leftJoin('clientes_promocoes_estornos', fn ($join) => $join->on('clientes_promocoes_estornos.clientes_transacoes_id', '=', 'clientes_transacoes.id')->whereNull('clientes_promocoes_estornos.deleted_at'))
            ->where('clientes_transacoes.origem', OrigemTransacao::Promoção->value)
            ->whereNull('clientes_transacoes.deleted_at')
            ->when(isset($filtros['clientes_promocoes_id']), fn (Builder $c) => $c->where('clientes_transacoes.referencia_id', (int) $filtros['clientes_promocoes_id']))
            ->when(isset($filtros['modalidade']), fn (Builder $c) => $c->where('clientes_transacoes.carteira', ModalidadePromocao::from($filtros['modalidade'])->carteira()->value));

        $this->buscar($consulta, $filtros);

        return $this->alcance->aplicar($consulta, $usuario, 'clientes_transacoes.clientes_id');
    }

    /**
     * Movimentos de bônus (conversão, expiração, exclusão) dos clientes da rede, ligados à promoção
     * pela referência, com a busca, a promoção, a modalidade (da promoção) e o período.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array{0: mixed, 1: mixed}|null  $periodo
     */
    private function movimentos(Usuarios $usuario, array $filtros, ?array $periodo): Builder
    {
        $consulta = DB::table('clientes_transacoes')
            ->join('clientes', 'clientes.id', '=', 'clientes_transacoes.clientes_id')
            ->join('clientes_promocoes', 'clientes_promocoes.id', '=', 'clientes_transacoes.referencia_id')
            ->whereNull('clientes_transacoes.deleted_at')
            ->when(isset($filtros['clientes_promocoes_id']), fn (Builder $c) => $c->where('clientes_transacoes.referencia_id', (int) $filtros['clientes_promocoes_id']))
            ->when(isset($filtros['modalidade']), fn (Builder $c) => $c->where('clientes_promocoes.modalidade', $filtros['modalidade']))
            ->when($periodo !== null, fn (Builder $c) => $c->whereBetween('clientes_transacoes.created_at', $periodo));

        $this->buscar($consulta, $filtros);

        return $this->alcance->aplicar($consulta, $usuario, 'clientes_transacoes.clientes_id');
    }

    /**
     * Busca pelo nome do cliente (trecho) ou, quando numérica, também pelo ID.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function buscar(Builder $consulta, array $filtros): void
    {
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca === '') {
            return;
        }

        $consulta->where(fn (Builder $c) => ctype_digit($busca)
            ? $c->where('clientes.id', (int) $busca)->orWhere('clientes.nome', 'like', '%'.$busca.'%')
            : $c->where('clientes.nome', 'like', '%'.$busca.'%'));
    }

    /**
     * CASE da situação (R-03), a mesma regra de EstornoBonus::situacao, e os valores dele.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function situacao_sql(): array
    {
        return [
            'CASE'
            .' WHEN clientes_promocoes_estornos.id IS NOT NULL THEN ?'
            .' WHEN clientes_rollovers.id IS NULL THEN ?'
            .' WHEN clientes_rollovers.convertido_em IS NOT NULL THEN ?'
            .' WHEN clientes_rollovers.expirado_em IS NOT NULL THEN ?'
            .' WHEN clientes_rollovers.excluido_em IS NOT NULL THEN ?'
            .' WHEN clientes_rollovers.cancelado_em IS NOT NULL THEN ?'
            .' WHEN clientes_rollovers.cumprido_em IS NOT NULL THEN ?'
            .' ELSE ? END',
            [
                SituacaoBonus::Estornado->value,
                SituacaoBonus::Cumprido->value,
                SituacaoBonus::Convertido->value,
                SituacaoBonus::Expirado->value,
                SituacaoBonus::ExcluídoPeloCliente->value,
                SituacaoBonus::Estornado->value,
                SituacaoBonus::Cumprido->value,
                SituacaoBonus::EmAndamento->value,
            ],
        ];
    }

    /**
     * @return array{quantidade: int, valor: string}
     */
    private static function total(object $linha): array
    {
        return ['quantidade' => (int) $linha->quantidade, 'valor' => bcadd((string) $linha->valor, '0', 2)];
    }

    /**
     * Início e fim do período (dias de Brasília) em UTC.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: mixed, 1: mixed}
     */
    private function periodo(array $filtros): array
    {
        return ListagemAfiliados::periodo($filtros['data_inicial'], $filtros['data_final']);
    }
}
