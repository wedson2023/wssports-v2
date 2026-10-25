<?php

namespace App\Services;

use App\Enums\CategoriaPromocao;
use App\Enums\Carteira;
use App\Enums\ModalidadePromocao;
use App\Enums\OrigemTransacao;
use App\Enums\SituacaoEstorno;
use App\Enums\SituacaoPromocao;
use App\Enums\TipoEstornoBonus;
use App\Models\ClientesPromocoes;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Aba Promoções da tela Bônus (spec 020, FR-004 a FR-009, research R-07): promoções da rede do usuário
 * (AlcancePromocoes), com a situação calculada no banco, busca, filtros e os números de uso da página.
 */
class ListagemPromocoes
{
    /**
     * Débitos que tiram o bônus do cliente sem passar pelo estorno do painel; as duas origens são só
     * de bônus. Os estornos vêm da tabela clientes_promocoes_estornos, porque a origem Estorno também
     * é usada nos estornos de apostas, com outra referência.
     */
    private const ORIGENS_RETIRADA = [OrigemTransacao::BônusExpirado, OrigemTransacao::BônusExcluído];

    public function __construct(
        private readonly AlcancePromocoes $alcance,
        private readonly AlcanceClientes $alcance_clientes,
    ) {}

    /**
     * Filtros da lista; os de antes (ativa, modalidade, categoria, por_pagina) continuam aceitos.
     *
     * @return array<string, mixed>
     */
    public function validar(Request $request): array
    {
        return $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'situacao' => ['nullable', Rule::enum(SituacaoPromocao::class)],
            'ativa' => ['nullable', 'boolean'],
            'modalidade' => ['nullable', Rule::enum(ModalidadePromocao::class)],
            'categoria' => ['nullable', Rule::enum(CategoriaPromocao::class)],
            'afiliados_id' => ['nullable', 'regex:/^(geral|\d+)$/'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ], [
            'busca.*' => 'A busca deve ter no máximo 150 caracteres.',
            'situacao.enum' => 'A situação informada é inválida.',
            'ativa.boolean' => 'O filtro ativa deve ser verdadeiro ou falso.',
            'modalidade.enum' => 'A modalidade informada é inválida.',
            'categoria.enum' => 'A categoria informada é inválida.',
            'afiliados_id.regex' => 'Afiliado não encontrado.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(Usuarios $usuario, array $filtros): Builder
    {
        [$situacao_sql, $situacao_valores] = self::situacao_sql();
        $busca = trim((string) ($filtros['busca'] ?? ''));
        $afiliado = $filtros['afiliados_id'] ?? null;

        $consulta = ClientesPromocoes::query()
            ->select('clientes_promocoes.*')
            ->selectRaw("{$situacao_sql} as situacao", $situacao_valores)
            ->selectRaw('exists (select 1 from clientes_transacoes where clientes_transacoes.origem = ? and clientes_transacoes.referencia_id = clientes_promocoes.id) as ja_aplicada', [OrigemTransacao::Promoção->value])
            ->with(['afiliado', 'autor_estorno'])
            ->when($busca !== '', fn (Builder $c) => $c->where('nome', 'like', '%'.$busca.'%'))
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->whereRaw("{$situacao_sql} = ?", [...$situacao_valores, $filtros['situacao']]))
            ->when(isset($filtros['ativa']), fn (Builder $c) => $c->where('ativa', (bool) $filtros['ativa']))
            ->when(isset($filtros['modalidade']), fn (Builder $c) => $c->where('modalidade', $filtros['modalidade']))
            ->when(isset($filtros['categoria']), fn (Builder $c) => $c->where('categoria', $filtros['categoria']))
            ->when($afiliado === 'geral', fn (Builder $c) => $c->whereNull('afiliados_id'))
            ->when($afiliado !== null && $afiliado !== 'geral', fn (Builder $c) => $c->where('afiliados_id', (int) $afiliado))
            ->orderByDesc('data_inicio')
            ->orderByDesc('id');

        return $this->alcance->aplicar($consulta, $usuario);
    }

    /**
     * Situação de uma promoção já carregada, pela mesma regra do CASE da lista.
     */
    public static function situacao(ClientesPromocoes $promocao): SituacaoPromocao
    {
        return match (true) {
            $promocao->estorno_situacao === SituacaoEstorno::EmAndamento => SituacaoPromocao::Estornando,
            $promocao->estorno_situacao === SituacaoEstorno::Concluído => SituacaoPromocao::Estornada,
            ! $promocao->ativa => SituacaoPromocao::Desativada,
            $promocao->data_inicio->isFuture() => SituacaoPromocao::Agendada,
            $promocao->data_fim !== null && $promocao->data_fim->isPast() => SituacaoPromocao::Encerrada,
            default => SituacaoPromocao::Vigente,
        };
    }

    /**
     * Números de uso das promoções informadas (FR-006): para quem não é Admin, só dos clientes da rede.
     *
     * @param  Collection<int, ClientesPromocoes>  $promocoes
     * @return array<int, array{clientes: int, creditado: string, cumpridos: int, convertido: string, retirado: string}>
     */
    public function uso(Collection $promocoes, Usuarios $usuario): array
    {
        $ids = $promocoes->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $movimentos = $this->na_rede(DB::table('clientes_transacoes'), $usuario, 'clientes_transacoes.clientes_id')
            ->whereNull('clientes_transacoes.deleted_at')
            ->whereIn('clientes_transacoes.referencia_id', $ids)
            ->whereIn('clientes_transacoes.origem', [OrigemTransacao::Promoção->value, OrigemTransacao::ConversãoBônus->value, ...array_map(fn (OrigemTransacao $origem) => $origem->value, self::ORIGENS_RETIRADA)])
            ->groupBy('clientes_transacoes.referencia_id')
            ->select('clientes_transacoes.referencia_id')
            ->selectRaw('COUNT(DISTINCT CASE WHEN origem = ? THEN clientes_id END) as clientes', [OrigemTransacao::Promoção->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN origem = ? THEN valor END), 0) as creditado', [OrigemTransacao::Promoção->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN origem = ? AND carteira = ? THEN valor END), 0) as convertido', [OrigemTransacao::ConversãoBônus->value, Carteira::Saldo->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN origem IN (?, ?) THEN valor END), 0) as retirado_sem_estorno', array_map(fn (OrigemTransacao $origem) => $origem->value, self::ORIGENS_RETIRADA))
            ->get()
            ->keyBy('referencia_id');

        // os automáticos já debitaram com a origem Bônus excluído, contada acima
        $estornados = $this->na_rede(DB::table('clientes_promocoes_estornos'), $usuario, 'clientes_promocoes_estornos.clientes_id')
            ->whereNull('deleted_at')
            ->whereIn('clientes_promocoes_id', $ids)
            ->where('tipo', '!=', TipoEstornoBonus::Automático->value)
            ->groupBy('clientes_promocoes_id')
            ->select('clientes_promocoes_id')
            ->selectRaw('COALESCE(SUM(valor_retirado), 0) as valor')
            ->pluck('valor', 'clientes_promocoes_id');

        $cumpridos = $this->na_rede(DB::table('clientes_transacoes'), $usuario, 'clientes_transacoes.clientes_id')
            ->leftJoin('clientes_rollovers', 'clientes_rollovers.clientes_transacoes_id', '=', 'clientes_transacoes.id')
            ->whereNull('clientes_transacoes.deleted_at')
            ->where('clientes_transacoes.origem', OrigemTransacao::Promoção->value)
            ->whereIn('clientes_transacoes.referencia_id', $ids)
            ->where(fn (QueryBuilder $c) => $c->whereNull('clientes_rollovers.id')->orWhereNotNull('clientes_rollovers.cumprido_em'))
            ->groupBy('clientes_transacoes.referencia_id')
            ->selectRaw('clientes_transacoes.referencia_id, COUNT(*) as quantidade')
            ->pluck('quantidade', 'referencia_id');

        return collect($ids)->mapWithKeys(fn (int $id) => [$id => [
            'clientes' => (int) ($movimentos[$id]->clientes ?? 0),
            'creditado' => bcadd((string) ($movimentos[$id]->creditado ?? '0'), '0', 2),
            'cumpridos' => (int) ($cumpridos[$id] ?? 0),
            'convertido' => bcadd((string) ($movimentos[$id]->convertido ?? '0'), '0', 2),
            'retirado' => bcadd((string) ($movimentos[$id]->retirado_sem_estorno ?? '0'), (string) ($estornados[$id] ?? '0'), 2),
        ]])->all();
    }

    /**
     * CASE da situação (R-07) e os valores dele, na ordem em que aparecem.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private static function situacao_sql(): array
    {
        $agora = now();

        return [
            'CASE'
            .' WHEN clientes_promocoes.estorno_situacao = ? THEN ?'
            .' WHEN clientes_promocoes.estorno_situacao = ? THEN ?'
            .' WHEN clientes_promocoes.ativa = 0 THEN ?'
            .' WHEN clientes_promocoes.data_inicio > ? THEN ?'
            .' WHEN clientes_promocoes.data_fim IS NOT NULL AND clientes_promocoes.data_fim < ? THEN ?'
            .' ELSE ? END',
            [
                SituacaoEstorno::EmAndamento->value, SituacaoPromocao::Estornando->value,
                SituacaoEstorno::Concluído->value, SituacaoPromocao::Estornada->value,
                SituacaoPromocao::Desativada->value,
                $agora, SituacaoPromocao::Agendada->value,
                $agora, SituacaoPromocao::Encerrada->value,
                SituacaoPromocao::Vigente->value,
            ],
        ];
    }

    /**
     * Restringe a consulta aos clientes da rede pela coluna informada (sem restrição para o Admin).
     */
    private function na_rede(QueryBuilder $consulta, Usuarios $usuario, string $coluna): QueryBuilder
    {
        return $this->alcance_clientes->aplicar($consulta, $usuario, $coluna);
    }
}
