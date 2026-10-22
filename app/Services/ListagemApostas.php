<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEncerramento;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\Usuarios;
use App\Support\CodigoAposta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lista de apostas do painel (spec 018, research R-02 a R-05). Uma consulta base única, com o
 * alcance por nível e todos os filtros, é usada pela lista, pelos totais, pela planilha e pelos
 * premiados, para que as quatro vejam exatamente as mesmas apostas (o antigo tinha uma consulta por
 * aba, com regras diferentes entre elas).
 */
class ListagemApostas
{
    /**
     * Prêmio exibido: o total a pagar (prêmio + acréscimo, na parte que continua valendo depois de
     * encerramentos parciais, como Apostas::total_a_pagar); na aposta encerrada, o valor pago.
     */
    public const EXPRESSAO_PREMIO = 'CASE WHEN apostas.resultado = ? THEN apostas.valor_pago '
        .'ELSE TRUNCATE((apostas.premio + apostas.valor_acrescido) * apostas.fracao_restante, 2) END';

    /**
     * Máximo de vendedores devolvidos pela busca do filtro.
     */
    private const MAXIMO_VENDEDORES = 20;

    public function __construct(
        private readonly AlcanceDashboard $alcance_dashboard,
        private readonly AlcanceApostas $alcance_apostas,
    ) {}

    /**
     * Consulta base: apostas que o usuário vê, com os filtros da tela.
     *
     * @param  array<string, mixed>  $filtros  formato de ApostasFiltrosRequest::filtros()
     */
    public function consulta(Usuarios $usuario, array $filtros): Builder
    {
        $consulta = Apostas::query();

        $this->aplicar_alcance($consulta, $usuario, $filtros['usuarios_id'] ?? null);

        $canceladas = $filtros['aba'] === 'canceladas';
        $consulta->where('apostas.situacao', $canceladas ? SituacaoAposta::Cancelada->value : SituacaoAposta::Ativa->value);

        $no_periodo = fn (Builder $q) => $q->whereBetween('apostas.created_at', [$filtros['inicio'], $filtros['fim']]);

        // busca: o nome do cliente respeita o período (salvo "buscar em todo o período"); um código
        // completo acha a aposta em qualquer data (FR-009)
        if ($filtros['nome'] === null) {
            $no_periodo($consulta);
        } else {
            $consulta->where(function (Builder $busca) use ($filtros, $no_periodo) {
                $busca->where(function (Builder $por_nome) use ($filtros, $no_periodo) {
                    $por_nome->where('apostas.nome', 'like', '%'.$this->escapar_like($filtros['nome']).'%');

                    if (! $filtros['todo_periodo']) {
                        $no_periodo($por_nome);
                    }
                });

                if ($filtros['codigo'] !== null) {
                    $busca->orWhere('apostas.codigo', $filtros['codigo']);
                }
            });
        }

        // mesma regra do ApuracaoCaixa: ao vivo = ao menos um palpite do ao vivo
        $palpite_ao_vivo = fn (QueryBuilder $subconsulta) => $subconsulta->select(DB::raw(1))
            ->from('apostas_palpites as tp')
            ->whereColumn('tp.apostas_id', 'apostas.id')
            ->whereNotNull('tp.confrontos_ao_vivo_id');

        $consulta
            ->when($filtros['aba'] === 'ao_vivo', fn (Builder $q) => $q->whereExists($palpite_ao_vivo))
            ->when($filtros['aba'] === 'pre_jogo', fn (Builder $q) => $q->whereNotExists($palpite_ao_vivo))
            ->when(! $canceladas && $filtros['resultado'] !== null, fn (Builder $q) => $q->where('apostas.resultado', $filtros['resultado']))
            ->when($filtros['forma_pagamento'] !== null, fn (Builder $q) => $q->where('apostas.forma_pagamento', $filtros['forma_pagamento']))
            ->when($filtros['premio_minimo'] !== null, fn (Builder $q) => $q->whereRaw(
                self::EXPRESSAO_PREMIO.' >= ?',
                [ResultadoAposta::Encerrada->value, $filtros['premio_minimo']],
            ));

        return $consulta;
    }

    /**
     * Página da lista, da mais nova para a mais antiga, com vendedor, cliente, prêmio exibido,
     * jogos restantes/total e quem cancelou (R-04). As linhas são models Apostas com as colunas
     * extras dos joins.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function lista(Usuarios $usuario, array $filtros, int $por_pagina): LengthAwarePaginator
    {
        return $this->com_colunas_da_lista($this->consulta($usuario, $filtros))
            ->orderByDesc('apostas.created_at')
            ->orderByDesc('apostas.id')
            ->paginate($por_pagina);
    }

    /**
     * Acrescenta à consulta base as colunas exibidas na lista e na planilha.
     */
    public function com_colunas_da_lista(Builder $consulta): Builder
    {
        $palpites_ativos = fn (bool $so_aguardando) => DB::table('apostas_palpites as jp')
            ->selectRaw('COUNT(*)')
            ->whereColumn('jp.apostas_id', 'apostas.id')
            ->where('jp.situacao', SituacaoPalpite::Ativo->value)
            ->when($so_aguardando, fn (QueryBuilder $q) => $q->where('jp.resultado', ResultadoAposta::Aguardando->value));

        return $consulta
            ->leftJoin('usuarios as v', 'v.id', '=', 'apostas.usuarios_id')
            ->leftJoin('usuarios as c', 'c.id', '=', 'apostas.cancelada_por')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'apostas.clientes_id')
            ->select('apostas.*')
            ->addSelect([
                'v.nome as vendedor_nome',
                'v.ativo as vendedor_ativo',
                'v.deleted_at as vendedor_excluido_em',
                'c.nome as cancelada_por_nome',
                'jogos_total' => $palpites_ativos(false),
                'jogos_restantes' => $palpites_ativos(true),
            ])
            ->selectRaw("COALESCE(NULLIF(apostas.nome, ''), cl.nome) as cliente_exibido")
            ->selectRaw(self::EXPRESSAO_PREMIO.' as premio_exibido', [ResultadoAposta::Encerrada->value]);
    }

    /**
     * Totais de todas as apostas do filtro (R-03, FR-012): as mesmas somas do Caixa
     * (ApuracaoCaixa::somas_apostas) e, para Admin e Supervisor, os encerramentos das apostas do
     * filtro. Na aba Canceladas, só a quantidade e o valor cancelado.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, int|string|null>
     */
    public function resumo(Usuarios $usuario, array $filtros): array
    {
        $base = $this->consulta($usuario, $filtros);

        if ($filtros['aba'] === 'canceladas') {
            $canceladas = $base->toBase()
                ->selectRaw('COUNT(*) as apostas, COALESCE(SUM(apostas.valor), 0) as valor_cancelado')
                ->first();

            return [
                'apostas' => (int) $canceladas->apostas,
                'valor_cancelado' => bcadd((string) $canceladas->valor_cancelado, '0', 2),
                'entradas' => null, 'comissao' => null, 'saidas' => null,
                'abertas' => null, 'em_aberto' => null, 'encerramentos' => null,
            ];
        }

        $somas = (clone $base)->toBase()
            ->selectRaw('COUNT(*) as apostas')
            ->selectRaw('COALESCE(SUM(apostas.valor), 0) as entradas')
            ->selectRaw('COALESCE(SUM(apostas.comissao), 0) as comissao')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN apostas.resultado = ? THEN apostas.premio WHEN apostas.resultado = ? THEN apostas.valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN apostas.resultado = ? THEN 1 ELSE 0 END), 0) as abertas', [ResultadoAposta::Aguardando->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN apostas.resultado = ? THEN apostas.valor ELSE 0 END), 0) as em_aberto', [ResultadoAposta::Aguardando->value])
            ->first();

        return [
            'apostas' => (int) $somas->apostas,
            'entradas' => bcadd((string) $somas->entradas, '0', 2),
            'comissao' => bcadd((string) $somas->comissao, '0', 2),
            'saidas' => bcadd((string) $somas->saidas, '0', 2),
            'abertas' => (int) $somas->abertas,
            'em_aberto' => bcadd((string) $somas->em_aberto, '0', 2),
            'encerramentos' => $this->ve_apostas_do_site($usuario) ? $this->encerramentos($base) : null,
            'valor_cancelado' => null,
        ];
    }

    /**
     * Vendedores da rede do usuário (inclusive excluídos) para o filtro, por nome ou login (R-10).
     *
     * @return Collection<int, array{id: int, nome: string, login: string, situacao: string}>
     */
    public function vendedores(Usuarios $usuario, ?string $busca): Collection
    {
        $ids = $this->alcance_dashboard->ids_vendedores($usuario);
        $busca = trim((string) $busca);

        return Usuarios::withTrashed()
            ->role(Funcao::Vendedor->value)
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('id', $ids))
            ->when($busca !== '', fn (Builder $q) => $q->where(fn (Builder $condicao) => $condicao
                ->where('nome', 'like', '%'.$this->escapar_like($busca).'%')
                ->orWhere('login', 'like', '%'.$this->escapar_like($busca).'%')))
            ->orderBy('nome')
            ->limit(self::MAXIMO_VENDEDORES)
            ->get(['id', 'nome', 'login', 'ativo', 'deleted_at'])
            ->map(fn (Usuarios $vendedor) => [
                'id' => $vendedor->id,
                'nome' => $vendedor->nome,
                'login' => $vendedor->login,
                'situacao' => $this->situacao_vendedor($vendedor->ativo, $vendedor->deleted_at),
            ]);
    }

    /**
     * Ações de uma linha da lista: ver sempre; cancelar com a permissão, o alcance de cancelamento
     * e a aposta ainda cancelável. As regras de tempo e de jogo iniciado ficam no
     * CancelamentoApostas, que decide ao cancelar.
     *
     * @return list<string>
     */
    public function acoes_da_linha(Usuarios $usuario, Apostas $aposta): array
    {
        return $this->pode_cancelar($usuario, $aposta) ? ['ver', 'cancelar'] : ['ver'];
    }

    /**
     * Aposta pelo código, se o usuário a vê na lista (mesmo alcance e situações); senão 404.
     */
    public function buscar_visivel(Usuarios $usuario, string $codigo): Apostas
    {
        $aposta = Apostas::query()
            ->where('apostas.codigo', CodigoAposta::normalizar($codigo))
            ->whereIn('apostas.situacao', [SituacaoAposta::Ativa->value, SituacaoAposta::Cancelada->value])
            ->tap(fn (Builder $consulta) => $this->aplicar_alcance($consulta, $usuario, null))
            ->first();

        abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

        return $aposta;
    }

    /**
     * Ações do bilhete pelo mesmo critério do servidor (R-05): cancelar, editar palpites e
     * imprimir a 2ª via.
     *
     * @return list<string>
     */
    public function acoes_do_bilhete(Usuarios $usuario, Apostas $aposta): array
    {
        $aguardando = $this->aguardando_resultado($aposta);

        return array_keys(array_filter([
            'cancelar' => $this->pode_cancelar($usuario, $aposta),
            'editar_palpites' => $aguardando
                && Funcao::usuario_pode($usuario, 'apostas.editar')
                && $this->alcance_apostas->pode_editar($usuario, $aposta),
            'imprimir_segunda_via' => $aguardando && Funcao::usuario_pode($usuario, 'apostas.imprimir_segunda_via'),
        ]));
    }

    /**
     * Aposta ativa ainda sem resultado: a única que pode ser cancelada, editada ou ter 2ª via.
     */
    public function aguardando_resultado(Apostas $aposta): bool
    {
        return $aposta->situacao === SituacaoAposta::Ativa && $aposta->resultado === ResultadoAposta::Aguardando;
    }

    /**
     * Situação do vendedor exibida na lista: excluído, inativo ou ativo.
     */
    public function situacao_vendedor(mixed $ativo, mixed $excluido_em): string
    {
        return match (true) {
            $excluido_em !== null => 'excluido',
            ! (bool) $ativo => 'inativo',
            default => 'ativo',
        };
    }

    /**
     * Admin e Supervisor veem as apostas dos clientes do site (FR-003), como no Caixa clientes.
     */
    public function ve_apostas_do_site(Usuarios $usuario): bool
    {
        return in_array($usuario->funcao(), [Funcao::Admin, Funcao::Supervisor], true);
    }

    /**
     * Alcance por nível (data-model §4): Admin, todas; Supervisor e Gerente, as dos vendedores da
     * sub-hierarquia (inclusive excluídos); Vendedor, as próprias; apostas do site só para Admin e
     * Supervisor. Com vendedor no filtro, ele precisa estar no alcance (o Vendedor não filtra).
     */
    private function aplicar_alcance(Builder $consulta, Usuarios $usuario, ?int $usuarios_id): void
    {
        $ids = $this->alcance_dashboard->ids_vendedores($usuario);
        $ve_site = $this->ve_apostas_do_site($usuario);

        if ($usuarios_id !== null && $usuario->funcao() !== Funcao::Vendedor) {
            $no_alcance = $ids === null
                ? Usuarios::withTrashed()->whereKey($usuarios_id)->exists()
                : in_array($usuarios_id, $ids, true);

            abort_unless($no_alcance, 403, 'Você não tem permissão para esta ação.');

            $consulta->where('apostas.usuarios_id', $usuarios_id);

            return;
        }

        $consulta->where(function (Builder $condicao) use ($ids, $ve_site) {
            $ids === null
                ? $condicao->whereNotNull('apostas.usuarios_id')
                : $condicao->whereIn('apostas.usuarios_id', $ids);

            if ($ve_site) {
                $condicao->orWhereNotNull('apostas.clientes_id');
            }
        });
    }

    private function pode_cancelar(Usuarios $usuario, Apostas $aposta): bool
    {
        return $this->aguardando_resultado($aposta)
            && Funcao::usuario_pode($usuario, 'apostas.cancelar')
            && $this->alcance_apostas->pode_cancelar($usuario, $aposta);
    }

    /**
     * Valor pago nos encerramentos (Registrado e Creditado, como a Caixa online) das apostas do
     * filtro.
     */
    private function encerramentos(Builder $base): string
    {
        $total = DB::table('apostas_encerramentos')
            ->whereIn('situacao', [SituacaoEncerramento::Registrado->value, SituacaoEncerramento::Creditado->value])
            ->whereIn('apostas_id', (clone $base)->toBase()->select('apostas.id'))
            ->sum('valor');

        return bcadd((string) $total, '0', 2);
    }

    private function escapar_like(string $texto): string
    {
        return addcslashes($texto, '\\%_');
    }
}
