<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\TipoLancamentoCaixa;
use App\Models\Usuarios;
use App\Models\UsuariosConfiguracoes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo único do caixa da rede (spec 017, R-02): somas das apostas do balcão por vendedor num
 * período e tipo, com as mesmas regras do Apurado da Dashboard (spec 014), lançamentos e recebido
 * no período, saldo de caixa (SaldoCaixa) e as linhas de gerente, supervisor e banca. A comissão do
 * gerente é calculada por gerente sobre o líquido dos vendedores dele no período inteiro, zero
 * quando ele for ≤ 0. Somas no banco (uma consulta por fonte); contas em bcmath.
 */
class ApuracaoCaixa
{
    /**
     * Quantidades da linha (inteiros).
     */
    private const QUANTIDADES = ['apostas', 'abertas', 'premiadas', 'perdidas'];

    /**
     * Valores da linha somados entre linhas (os de caixa ficam nulos fora do tipo Todas).
     */
    private const VALORES = ['entradas', 'em_aberto', 'valor_premiadas', 'valor_perdidas', 'saidas', 'comissao_vendedor'];

    private const VALORES_CAIXA = ['lancamentos', 'retiradas', 'saldo_caixa', 'recebido_periodo'];

    public function __construct(private readonly SaldoCaixa $saldo) {}

    /**
     * Usuários da rede do foco (o próprio e toda a sub-hierarquia, inclusive inativos e excluídos;
     * null = a banca toda), indexados pelo id.
     *
     * @return array<int, array{id: int, nome: string, login: string, superior: ?int, funcao: ?string, situacao: string}>
     */
    public function rede(?Usuarios $foco): array
    {
        $consulta = Usuarios::withTrashed()->with('roles');

        if ($foco !== null) {
            $consulta->whereIn('id', [$foco->id, ...$foco->ids_sub_hierarquia_com_excluidos()]);
        }

        return $consulta->get(['id', 'nome', 'login', 'usuarios_id', 'ativo', 'deleted_at'])
            ->mapWithKeys(fn (Usuarios $usuario) => [$usuario->id => [
                'id' => $usuario->id,
                'nome' => $usuario->nome,
                'login' => $usuario->login,
                'superior' => $usuario->usuarios_id,
                'funcao' => $usuario->funcao()?->value,
                'situacao' => $usuario->trashed() ? 'excluido' : ($usuario->ativo ? 'ativo' : 'inativo'),
            ]])
            ->all();
    }

    /**
     * Apuração completa do foco: linhas de vendedores, gerentes e supervisores da rede e o total
     * do foco (a banca quando null).
     *
     * @return array{rede: array<int, array>, vendedores: array<int, array>, gerentes: array<int, array>, supervisores: array<int, array>, total: array}
     */
    public function apurar(?Usuarios $foco, Carbon $inicio, Carbon $fim, string $tipo): array
    {
        $rede = $this->rede($foco);
        $ids_por_funcao = fn (Funcao $funcao) => array_keys(array_filter($rede, fn (array $usuario) => $usuario['funcao'] === $funcao->value));

        $vendedores = $this->linhas_vendedores($ids_por_funcao(Funcao::Vendedor), $inicio, $fim, $tipo);
        $percentuais = $this->percentuais_gerentes($ids_por_funcao(Funcao::Gerente));

        $gerentes = [];

        foreach ($ids_por_funcao(Funcao::Gerente) as $id) {
            $do_gerente = array_filter($vendedores, fn (array $linha, int $id_vendedor) => $rede[$id_vendedor]['superior'] === $id, ARRAY_FILTER_USE_BOTH);
            $gerentes[$id] = $this->linha_gerente($do_gerente, $percentuais[$id] ?? '0.00', $tipo === 'todas');
        }

        $supervisores = [];

        foreach ($ids_por_funcao(Funcao::Supervisor) as $id) {
            $dos_gerentes = array_filter($gerentes, fn (array $linha, int $id_gerente) => $rede[$id_gerente]['superior'] === $id, ARRAY_FILTER_USE_BOTH);
            $supervisores[$id] = $this->somar($dos_gerentes, $tipo === 'todas');
        }

        $total = match ($foco?->funcao()) {
            Funcao::Vendedor => $vendedores[$foco->id] ?? $this->linha_vazia($tipo === 'todas', false),
            Funcao::Gerente => $gerentes[$foco->id],
            Funcao::Supervisor => $supervisores[$foco->id],
            default => $this->total_banca($rede, $vendedores, $gerentes, $tipo === 'todas'),
        };

        return compact('rede', 'vendedores', 'gerentes', 'supervisores', 'total');
    }

    /**
     * Linhas do nível abaixo do foco (banca → supervisores; supervisor → gerentes; gerente →
     * vendedores; vendedor → ele mesmo), com nome, login e situação. Inativos e excluídos só entram
     * com movimento ou saldo de caixa. Usado pela lista e pela exportação, para as duas baterem.
     *
     * @param  array{rede: array<int, array>, vendedores: array<int, array>, gerentes: array<int, array>, supervisores: array<int, array>}  $apuracao
     * @return array{funcao: Funcao, linhas: list<array<string, mixed>>}
     */
    public function linhas_do_nivel(array $apuracao, ?Usuarios $foco): array
    {
        [$funcao, $linhas_do_nivel] = match ($foco?->funcao()) {
            null, Funcao::Admin => [Funcao::Supervisor, $apuracao['supervisores']],
            Funcao::Supervisor => [Funcao::Gerente, $apuracao['gerentes']],
            Funcao::Gerente => [Funcao::Vendedor, $apuracao['vendedores']],
            Funcao::Vendedor => [Funcao::Vendedor, array_intersect_key($apuracao['vendedores'], [$foco->id => true])],
        };

        $linhas = [];

        foreach ($linhas_do_nivel as $id => $linha) {
            $usuario = $apuracao['rede'][$id];

            // no foco Supervisor/Gerente, só os subordinados diretos dele
            if ($foco !== null && $foco->funcao() !== Funcao::Vendedor && $usuario['superior'] !== $foco->id) {
                continue;
            }

            if ($usuario['situacao'] !== 'ativo' && ! $this->tem_movimento($linha)) {
                continue;
            }

            $linhas[] = ['id' => $id, 'nome' => $usuario['nome'], 'login' => $usuario['login'], 'situacao' => $usuario['situacao'], ...$linha];
        }

        return ['funcao' => $funcao, 'linhas' => $linhas];
    }

    /**
     * Limites geral, duplo e simples dos vendedores (sem configuração = sem limites).
     *
     * @param  list<int>  $ids
     * @return array<int, array{geral: string, duplo: string, simples: string}>
     */
    public function limites(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return UsuariosConfiguracoes::whereIn('usuarios_id', $ids)
            ->get(['usuarios_id', ...UsuariosConfiguracoes::LIMITES_VENDA])
            ->mapWithKeys(fn (UsuariosConfiguracoes $configuracao) => [$configuracao->usuarios_id => [
                'geral' => (string) $configuracao->limite_geral,
                'duplo' => (string) $configuracao->limite_duplo,
                'simples' => (string) $configuracao->limite_simples,
            ]])
            ->all();
    }

    /**
     * Teve apostas, lançamentos, recebimento ou tem saldo de caixa.
     *
     * @param  array<string, mixed>  $linha
     */
    private function tem_movimento(array $linha): bool
    {
        if ($linha['apostas'] > 0) {
            return true;
        }

        foreach (self::VALORES_CAIXA as $campo) {
            if ($linha[$campo] !== null && bccomp($linha[$campo], '0', 2) !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Linha de cada vendedor no período e tipo; lançamentos, retiradas, saldo de caixa e recebido
     * só no tipo Todas (não são por tipo de aposta).
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function linhas_vendedores(array $ids, Carbon $inicio, Carbon $fim, string $tipo): array
    {
        if ($ids === []) {
            return [];
        }

        $com_caixa = $tipo === 'todas';
        $somas = $this->somas_apostas($ids, $inicio, $fim, $tipo);
        $lancamentos = $com_caixa ? $this->lancamentos_por_vendedor($ids, $inicio, $fim) : [];
        $recebido = $com_caixa ? $this->recebido_por_vendedor($ids, $inicio, $fim) : [];
        $saldos = $com_caixa ? $this->saldo->saldos($ids) : [];

        $linhas = [];

        foreach ($ids as $id) {
            $linha = $this->linha_vazia($com_caixa, false);
            $soma = $somas[$id] ?? null;

            if ($soma !== null) {
                foreach (self::QUANTIDADES as $campo) {
                    $linha[$campo] = (int) $soma->{$campo};
                }

                foreach (self::VALORES as $campo) {
                    $linha[$campo] = bcadd((string) $soma->{$campo}, '0', 2);
                }
            }

            if ($com_caixa) {
                $linha['lancamentos'] = $lancamentos[$id]['lancamentos'] ?? '0.00';
                $linha['retiradas'] = $lancamentos[$id]['retiradas'] ?? '0.00';
                $linha['recebido_periodo'] = $recebido[$id] ?? '0.00';
                $linha['saldo_caixa'] = $saldos[$id] ?? '0.00';
            }

            $linha['liquido'] = bcsub(bcsub($linha['entradas'], $linha['saidas'], 2), $linha['comissao_vendedor'], 2);
            $linhas[$id] = $linha;
        }

        return $linhas;
    }

    /**
     * Linha do gerente: soma dos vendedores dele e a comissão do gerente sobre o líquido deles.
     *
     * @param  array<int, array<string, mixed>>  $linhas_vendedores
     * @return array<string, mixed>
     */
    public function linha_gerente(array $linhas_vendedores, string $percentual, bool $com_caixa): array
    {
        $linha = $this->somar($linhas_vendedores, $com_caixa);
        $comissao = $this->comissao_gerente($linha['liquido'], $percentual);

        $linha['comissao_gerente'] = $comissao;
        $linha['liquido'] = bcsub($linha['liquido'], $comissao, 2);

        return $linha;
    }

    /**
     * Comissão do gerente: percentual sobre o líquido dos vendedores dele, zero se ≤ 0.
     */
    public function comissao_gerente(string $liquido_vendedores, string $percentual): string
    {
        if (bccomp($liquido_vendedores, '0', 2) <= 0) {
            return '0.00';
        }

        return bcdiv(bcmul($liquido_vendedores, $percentual, 4), '100', 2);
    }

    /**
     * Soma das linhas (a comissão do gerente e o líquido também somam).
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    public function somar(array $linhas, bool $com_caixa): array
    {
        $total = $this->linha_vazia($com_caixa, true);

        foreach ($linhas as $linha) {
            foreach (self::QUANTIDADES as $campo) {
                $total[$campo] += $linha[$campo];
            }

            foreach ([...self::VALORES, 'liquido'] as $campo) {
                $total[$campo] = bcadd($total[$campo], $linha[$campo], 2);
            }

            $total['comissao_gerente'] = bcadd($total['comissao_gerente'], $linha['comissao_gerente'] ?? '0.00', 2);

            if ($com_caixa) {
                foreach (self::VALORES_CAIXA as $campo) {
                    $total[$campo] = bcadd($total[$campo], $linha[$campo], 2);
                }
            }
        }

        return $total;
    }

    /**
     * Percentual da comissão de cada gerente (0 quando não configurado).
     *
     * @param  list<int>  $ids_gerentes
     * @return array<int, string>
     */
    public function percentuais_gerentes(array $ids_gerentes): array
    {
        if ($ids_gerentes === []) {
            return [];
        }

        return UsuariosConfiguracoes::whereIn('usuarios_id', $ids_gerentes)
            ->pluck('percentual_comissao_gerente', 'usuarios_id')
            ->map(fn ($percentual) => (string) $percentual)
            ->all();
    }

    /**
     * Suprimentos e retiradas não excluídos com data no intervalo, por vendedor (ids null = todos).
     *
     * @param  list<int>|null  $ids
     * @return array<int, array{lancamentos: string, retiradas: string}>
     */
    public function lancamentos_por_vendedor(?array $ids, Carbon $inicio, Carbon $fim): array
    {
        return DB::table('caixa_lancamentos')
            ->when($ids !== null, fn (Builder $consulta) => $consulta->whereIn('usuarios_id', $ids))
            ->whereNull('deleted_at')
            ->whereBetween('ocorrido_em', [$inicio, $fim])
            ->groupBy('usuarios_id')
            ->select('usuarios_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END), 0) as lancamentos', [TipoLancamentoCaixa::Suprimento->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END), 0) as retiradas', [TipoLancamentoCaixa::Retirada->value])
            ->get()
            ->mapWithKeys(fn (object $linha) => [$linha->usuarios_id => [
                'lancamentos' => bcadd((string) $linha->lancamentos, '0', 2),
                'retiradas' => bcadd((string) $linha->retiradas, '0', 2),
            ]])
            ->all();
    }

    /**
     * Valor recebido nas prestações não desfeitas criadas no intervalo, por vendedor.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function recebido_por_vendedor(array $ids, Carbon $inicio, Carbon $fim): array
    {
        return DB::table('caixa_prestacoes_contas')
            ->whereIn('usuarios_id', $ids)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$inicio, $fim])
            ->groupBy('usuarios_id')
            ->select('usuarios_id')
            ->selectRaw('COALESCE(SUM(valor_recebido), 0) as total')
            ->pluck('total', 'usuarios_id')
            ->map(fn ($valor) => bcadd((string) $valor, '0', 2))
            ->all();
    }

    /**
     * Somas das apostas ativas do balcão feitas no intervalo, agrupadas por vendedor (regras do
     * Apurado da Dashboard). Ao vivo = tem palpite do ao vivo; pré-jogo = não tem.
     *
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    private function somas_apostas(array $ids, Carbon $inicio, Carbon $fim, string $tipo): array
    {
        $quantidade_por_resultado = 'COALESCE(SUM(CASE WHEN a.resultado = ? THEN 1 ELSE 0 END), 0)';
        $valor_por_resultado = 'COALESCE(SUM(CASE WHEN a.resultado = ? THEN a.valor ELSE 0 END), 0)';
        $palpite_ao_vivo = fn (Builder $consulta) => $consulta->select(DB::raw(1))
            ->from('apostas_palpites as p')
            ->whereColumn('p.apostas_id', 'a.id')
            ->whereNotNull('p.confrontos_ao_vivo_id');

        return DB::table('apostas as a')
            ->whereIn('a.usuarios_id', $ids)
            ->whereNull('a.clientes_id')
            ->whereNull('a.deleted_at')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->whereBetween('a.created_at', [$inicio, $fim])
            ->when($tipo === 'ao_vivo', fn (Builder $consulta) => $consulta->whereExists($palpite_ao_vivo))
            ->when($tipo === 'pre_jogo', fn (Builder $consulta) => $consulta->whereNotExists($palpite_ao_vivo))
            ->groupBy('a.usuarios_id')
            ->select('a.usuarios_id')
            ->selectRaw('COUNT(*) as apostas')
            ->selectRaw($quantidade_por_resultado.' as abertas', [ResultadoAposta::Aguardando->value])
            ->selectRaw($quantidade_por_resultado.' as premiadas', [ResultadoAposta::Vencedor->value])
            ->selectRaw($quantidade_por_resultado.' as perdidas', [ResultadoAposta::Perdedor->value])
            ->selectRaw('COALESCE(SUM(a.valor), 0) as entradas')
            ->selectRaw($valor_por_resultado.' as em_aberto', [ResultadoAposta::Aguardando->value])
            ->selectRaw($valor_por_resultado.' as valor_premiadas', [ResultadoAposta::Vencedor->value])
            ->selectRaw($valor_por_resultado.' as valor_perdidas', [ResultadoAposta::Perdedor->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN a.resultado = ? THEN a.premio WHEN a.resultado = ? THEN a.valor ELSE 0 END), 0) as saidas',
                [ResultadoAposta::Vencedor->value, ResultadoAposta::Devolvido->value],
            )
            ->selectRaw('COALESCE(SUM(a.comissao), 0) as comissao_vendedor')
            ->get()
            ->keyBy('usuarios_id')
            ->all();
    }

    /**
     * Total da banca: gerentes da rede mais os vendedores sem gerente na rede.
     *
     * @param  array<int, array>  $rede
     * @param  array<int, array>  $vendedores
     * @param  array<int, array>  $gerentes
     * @return array<string, mixed>
     */
    private function total_banca(array $rede, array $vendedores, array $gerentes, bool $com_caixa): array
    {
        $sem_gerente = array_filter($vendedores, fn (array $linha, int $id) => ! isset($gerentes[$rede[$id]['superior']]), ARRAY_FILTER_USE_BOTH);

        return $this->somar([...array_values($gerentes), ...array_values($sem_gerente)], $com_caixa);
    }

    /**
     * Linha zerada; a comissão do gerente é nula na linha do vendedor.
     *
     * @return array<string, mixed>
     */
    private function linha_vazia(bool $com_caixa, bool $com_comissao_gerente): array
    {
        return [
            ...array_fill_keys(self::QUANTIDADES, 0),
            ...array_fill_keys(self::VALORES, '0.00'),
            'comissao_gerente' => $com_comissao_gerente ? '0.00' : null,
            ...array_fill_keys(self::VALORES_CAIXA, $com_caixa ? '0.00' : null),
            'liquido' => '0.00',
        ];
    }
}
