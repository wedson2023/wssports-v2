<?php

namespace App\Services;

use App\Enums\SituacaoAfiliado;
use App\Enums\SituacaoDeposito;
use App\Enums\SituacaoSaque;
use Illuminate\Contracts\Database\Query\Builder as BuilderContrato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas da gestão e do painel do afiliado (spec 012, R-15): totais do período, extrato e
 * clientes indicados, sempre pelo query builder (sem SQL montado com dados da requisição).
 */
class ListagemAfiliados
{
    /**
     * Fuso do "dia" dos filtros de período, como nas demais telas (spec 007).
     */
    public const FUSO = 'America/Sao_Paulo';

    /**
     * Grupos de situação das abas da tela (spec 022, R-02): Ativos (Ativo e Inativo) e Em análise
     * (Em análise e Recusado).
     */
    public const GRUPOS = [
        'aprovados' => [SituacaoAfiliado::Ativo, SituacaoAfiliado::Inativo],
        'analise' => [SituacaoAfiliado::EmAnálise, SituacaoAfiliado::Recusado],
    ];

    /**
     * Início e fim (em UTC) do período informado em dias locais; sem datas, o dia de hoje ou, com
     * $padrao "mes", do dia 1 do mês até hoje (listagem da gestão, spec 022).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodo(?string $de, ?string $ate, string $padrao = 'hoje'): array
    {
        $hoje = now(self::FUSO)->toDateString();
        $inicio_padrao = $padrao === 'mes' ? now(self::FUSO)->startOfMonth()->toDateString() : $hoje;

        return [
            Carbon::parse($de ?? $inicio_padrao, self::FUSO)->startOfDay()->utc(),
            Carbon::parse($ate ?? ($de === null ? $hoje : $de), self::FUSO)->endOfDay()->utc(),
        ];
    }

    /**
     * Filtros comuns da lista e do resumo da gestão: grupo, situação, gerente, sem gerente e busca.
     *
     * @param  array{grupo?: ?string, situacao?: ?string, busca?: ?string, usuarios_id?: ?int, sem_gerente?: ?bool}  $filtros
     */
    public function filtrar_gestao(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(isset($filtros['grupo']), fn (Builder $c) => $c->whereIn('afiliados.situacao', self::GRUPOS[$filtros['grupo']]))
            ->when(isset($filtros['situacao']), fn (Builder $c) => $c->where('afiliados.situacao', $filtros['situacao']))
            ->when(isset($filtros['usuarios_id']), fn (Builder $c) => $c->where('afiliados.usuarios_id', $filtros['usuarios_id']))
            ->when((bool) ($filtros['sem_gerente'] ?? false), fn (Builder $c) => $c->whereNull('afiliados.usuarios_id'))
            ->when(isset($filtros['busca']), fn (Builder $c) => $c->where(fn (Builder $busca) => $busca
                ->where('afiliados.nome', 'like', '%'.$filtros['busca'].'%')
                ->orWhere('afiliados.login', 'like', $filtros['busca'].'%')
                ->orWhere('afiliados.codigo', 'like', mb_strtoupper($filtros['busca']).'%')));
    }

    /**
     * Listagem da gestão (FR-043 e FR-044 da spec 012): filtros e, por afiliado, cadastros,
     * depósitos, saques e comissão do período (padrão: este mês), entradas e valor a receber (em
     * aberto) e última prestação, tudo por subconsultas. Em análise vem do cadastro mais antigo para
     * o mais novo; as demais, por nome (spec 022).
     *
     * @param  array{grupo?: ?string, situacao?: ?string, busca?: ?string, usuarios_id?: ?int, sem_gerente?: ?bool, de?: ?string, ate?: ?string}  $filtros
     */
    public function consulta_gestao(Builder $consulta, array $filtros): Builder
    {
        [$inicio, $fim] = self::periodo($filtros['de'] ?? null, $filtros['ate'] ?? null, 'mes');
        $periodo = [$inicio, $fim];
        $em_analise = ($filtros['grupo'] ?? null) === 'analise';

        return $this->filtrar_gestao($consulta, $filtros)
            ->with(['gerente', 'comissao_vigente', 'ultima_prestacao', 'autor_cadastro', 'autor_decisao'])
            ->withCount(['clientes as cadastros_periodo' => fn ($c) => $c->withTrashed()->whereBetween('clientes.created_at', $periodo)])
            ->addSelect([
                'depositos_periodo' => DB::table('depositos')
                    ->join('clientes', 'clientes.id', '=', 'depositos.clientes_id')
                    ->selectRaw('COALESCE(SUM(depositos.valor - depositos.valor_estornado), 0)')
                    ->whereColumn('clientes.afiliados_id', 'afiliados.id')
                    ->whereIn('depositos.situacao', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value])
                    ->whereNull('depositos.deleted_at')
                    ->whereBetween('depositos.pago_em', $periodo),
                'saques_periodo' => DB::table('saques')
                    ->join('clientes', 'clientes.id', '=', 'saques.clientes_id')
                    ->selectRaw('COALESCE(SUM(saques.valor), 0)')
                    ->whereColumn('clientes.afiliados_id', 'afiliados.id')
                    ->where('saques.situacao', SituacaoSaque::Pago->value)
                    ->whereNull('saques.deleted_at')
                    ->whereBetween('saques.pago_em', $periodo),
            ])
            ->withSum(['lancamentos as comissao_periodo' => fn ($c) => $c->whereBetween('ocorrido_em', $periodo)], 'valor')
            ->withSum(['lancamentos as valor_a_receber' => fn ($c) => $c->emAberto()], 'valor')
            ->withSum(['lancamentos as entradas' => fn ($c) => $c->emAberto()], 'base')
            ->when($em_analise, fn (Builder $c) => $c->orderBy('afiliados.created_at'), fn (Builder $c) => $c->orderBy('afiliados.nome'))
            ->orderBy('afiliados.id');
    }

    /**
     * Cartões da tela (spec 022, FR-004, R-16), por consultas agregadas: total de Ativos e Inativos,
     * comissão do período e a receber dos afiliados do filtro (rede, busca, gerente); Em análise conta
     * todos os pendentes da rede, sem busca nem filtros, só quando $com_em_analise.
     *
     * @param  Builder  $consulta  afiliados já com o alcance aplicado
     * @param  array{busca?: ?string, usuarios_id?: ?int, sem_gerente?: ?bool, de?: ?string, ate?: ?string}  $filtros
     * @return array{total_afiliados: int, em_analise: ?int, comissao_periodo: string, a_receber: string, periodo: array{de: string, ate: string}}
     */
    public function resumo(Builder $consulta, array $filtros, bool $com_em_analise): array
    {
        [$inicio, $fim] = self::periodo($filtros['de'] ?? null, $filtros['ate'] ?? null, 'mes');

        $aprovados = $this->filtrar_gestao($consulta->clone(), [...$filtros, 'grupo' => 'aprovados']);
        $ids = $aprovados->clone()->select('afiliados.id')->toBase();
        $soma = fn (callable $restringir) => bcadd((string) ($restringir(
            DB::table('afiliados_lancamentos')->whereIn('afiliados_id', $ids)->whereNull('deleted_at'),
        )->sum('valor') ?? '0'), '0', 2);

        return [
            'total_afiliados' => $aprovados->count(),
            'em_analise' => $com_em_analise
                ? $consulta->clone()->where('afiliados.situacao', SituacaoAfiliado::EmAnálise)->count()
                : null,
            'comissao_periodo' => $soma(fn ($c) => $c->whereBetween('ocorrido_em', [$inicio, $fim])),
            'a_receber' => $soma(fn ($c) => $c->whereNull('afiliados_prestacoes_contas_id')),
            'periodo' => ['de' => $inicio->clone()->setTimezone(self::FUSO)->toDateString(), 'ate' => $fim->clone()->setTimezone(self::FUSO)->toDateString()],
        ];
    }

    /**
     * Filtros do extrato: período (por ocorrido_em) e situação (aberto ou prestado); mais recentes
     * primeiro.
     *
     * @param  array{de?: ?string, ate?: ?string, situacao?: ?string, clientes_id?: ?int}  $filtros
     */
    public function filtrar_lancamentos(HasMany|Builder $consulta, array $filtros): HasMany|Builder
    {
        $tem_periodo = isset($filtros['de']) || isset($filtros['ate']);
        [$inicio, $fim] = self::periodo($filtros['de'] ?? null, $filtros['ate'] ?? null);

        return $consulta
            ->when($tem_periodo, fn ($c) => $c->whereBetween('ocorrido_em', [$inicio, $fim]))
            ->when(($filtros['situacao'] ?? null) === 'aberto', fn ($c) => $c->whereNull('afiliados_prestacoes_contas_id'))
            ->when(($filtros['situacao'] ?? null) === 'prestado', fn ($c) => $c->whereNotNull('afiliados_prestacoes_contas_id'))
            ->when(isset($filtros['clientes_id']), fn ($c) => $c->where('clientes_id', $filtros['clientes_id']))
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id');
    }

    /**
     * Total depositado pelo cliente (aprovados e estornados, menos o estornado), como coluna da
     * consulta de clientes.
     */
    public static function subconsulta_total_depositado(?Carbon $inicio = null, ?Carbon $fim = null): BuilderContrato
    {
        return DB::table('depositos')
            ->selectRaw('COALESCE(SUM(valor - valor_estornado), 0)')
            ->whereColumn('depositos.clientes_id', 'clientes.id')
            ->whereIn('situacao', [SituacaoDeposito::Aprovado->value, SituacaoDeposito::Estornado->value])
            ->whereNull('depositos.deleted_at')
            ->when($inicio !== null, fn ($c) => $c->whereBetween('pago_em', [$inicio, $fim]));
    }

    /**
     * Regras dos filtros do extrato, comuns ao painel do afiliado e à gestão.
     *
     * @return array<string, list<string>>
     */
    public static function regras_extrato(): array
    {
        return [
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'situacao' => ['nullable', 'in:aberto,prestado'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens_extrato(): array
    {
        return [
            '*.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'situacao.in' => 'A situação deve ser aberto ou prestado.',
            'por_pagina.integer' => 'O campo por página deve ser um número inteiro.',
            'por_pagina.between' => 'O campo por página deve estar entre 1 e 100.',
        ];
    }
}
