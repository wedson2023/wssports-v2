<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Models\Usuarios;
use App\Support\NomesCotacoes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Seções de apostas do balcão da Dashboard (spec 014, research R-05 e R-06): situação dos jogos,
 * resumo das apostas, apostas por um jogo e os rankings de vendedores, jogos e opções, sempre na
 * rede do usuário. Somas e contagens no banco.
 */
class ApostasDashboard
{
    private const LIMITE_RANKING = 10;

    private const LIMITE_UM_JOGO = 50;

    public function __construct(private readonly AlcanceDashboard $alcance) {}

    /**
     * Jogos distintos das apostas do dia por situação; Bloqueado conta como Aguardando.
     *
     * @return array{total: int, aguardando: int, encerrados: int, adiados: int, cancelados: int}
     */
    public function situacao_jogos(Usuarios $usuario): array
    {
        $contar = 'COUNT(DISTINCT CASE WHEN c.situacao = ? THEN c.id END)';

        // todos os palpites, como no antigo: o jogo cancelado cancela o palpite dele ("Jogo
        // cancelado") e, sem isso, nunca entraria em Cancelados
        $linha = $this->apostas_do_balcao($usuario, ...$this->alcance->periodo_do_dia())
            ->join('apostas_palpites as p', 'p.apostas_id', '=', 'a.id')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->whereNull('p.deleted_at')
            ->join('confrontos as c', 'c.id', '=', 'p.confrontos_id')
            ->selectRaw('COUNT(DISTINCT c.id) as total')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN c.situacao IN (?, ?) THEN c.id END) as aguardando',
                [SituacaoConfronto::Aguardando->value, SituacaoConfronto::Bloqueado->value],
            )
            ->selectRaw($contar.' as encerrados', [SituacaoConfronto::Encerrado->value])
            ->selectRaw($contar.' as adiados', [SituacaoConfronto::Adiado->value])
            ->selectRaw($contar.' as cancelados', [SituacaoConfronto::Cancelado->value])
            ->first();

        return [
            'total' => (int) $linha->total,
            'aguardando' => (int) $linha->aguardando,
            'encerrados' => (int) $linha->encerrados,
            'adiados' => (int) $linha->adiados,
            'cancelados' => (int) $linha->cancelados,
        ];
    }

    /**
     * Quantidade e valor das apostas do dia: ativas por resultado e canceladas (pela data da aposta).
     *
     * @return array<string, array{quantidade: int, valor: string}>
     */
    public function resumo_apostas(Usuarios $usuario): array
    {
        $ativa = SituacaoAposta::Ativa->value;
        $quantidade = 'COALESCE(SUM(CASE WHEN a.situacao = ? AND a.resultado = ? THEN 1 ELSE 0 END), 0)';
        $valor = 'COALESCE(SUM(CASE WHEN a.situacao = ? AND a.resultado = ? THEN a.valor ELSE 0 END), 0)';

        $linha = $this->apostas_do_balcao($usuario, ...$this->alcance->periodo_do_dia())
            ->whereIn('a.situacao', [$ativa, SituacaoAposta::Cancelada->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.situacao = ? THEN 1 ELSE 0 END), 0) as total_quantidade', [$ativa])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.situacao = ? THEN a.valor ELSE 0 END), 0) as total_valor', [$ativa])
            ->selectRaw($quantidade.' as abertas_quantidade', [$ativa, ResultadoAposta::Aguardando->value])
            ->selectRaw($valor.' as abertas_valor', [$ativa, ResultadoAposta::Aguardando->value])
            ->selectRaw($quantidade.' as premiadas_quantidade', [$ativa, ResultadoAposta::Vencedor->value])
            ->selectRaw($valor.' as premiadas_valor', [$ativa, ResultadoAposta::Vencedor->value])
            ->selectRaw($quantidade.' as perdidas_quantidade', [$ativa, ResultadoAposta::Perdedor->value])
            ->selectRaw($valor.' as perdidas_valor', [$ativa, ResultadoAposta::Perdedor->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.situacao = ? THEN 1 ELSE 0 END), 0) as canceladas_quantidade', [SituacaoAposta::Cancelada->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.situacao = ? THEN a.valor ELSE 0 END), 0) as canceladas_valor', [SituacaoAposta::Cancelada->value])
            ->first();

        $resumo = [];

        foreach (['total', 'abertas', 'premiadas', 'perdidas', 'canceladas'] as $grupo) {
            $resumo[$grupo] = [
                'quantidade' => (int) $linha->{"{$grupo}_quantidade"},
                'valor' => bcadd((string) $linha->{"{$grupo}_valor"}, '0', 2),
            ];
        }

        return $resumo;
    }

    /**
     * Apostas abertas de ontem e hoje que dependem de um único item para fechar: um jogo ainda não
     * decidido (palpites do mesmo jogo, como no Criar Aposta, contam uma vez) ou um especial pendente.
     *
     * @return list<array{codigo: string, apostador: string, valor: string, premio: string}>
     */
    public function apostas_um_jogo(Usuarios $usuario): array
    {
        $pendentes = 'COUNT(DISTINCT CASE WHEN p.confrontos_id IS NOT NULL AND c.situacao IN (?, ?) THEN p.confrontos_id END)'
            .' + COALESCE(SUM(CASE WHEN p.especiais_id IS NOT NULL AND p.resultado = ? THEN 1 ELSE 0 END), 0)';

        return $this->apostas_do_balcao($usuario, ...$this->alcance->periodo_de_ontem_e_hoje())
            ->join('apostas_palpites as p', function ($juncao) {
                $juncao->on('p.apostas_id', '=', 'a.id')
                    ->where('p.situacao', SituacaoPalpite::Ativo->value)
                    ->whereNull('p.deleted_at');
            })
            ->leftJoin('confrontos as c', 'c.id', '=', 'p.confrontos_id')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->where('a.resultado', ResultadoAposta::Aguardando->value)
            ->groupBy('a.id', 'a.codigo', 'a.nome', 'a.valor', 'a.premio')
            ->havingRaw($pendentes.' = 1', [SituacaoConfronto::Aguardando->value, SituacaoConfronto::Bloqueado->value, ResultadoAposta::Aguardando->value])
            ->orderByDesc('a.premio')
            ->limit(self::LIMITE_UM_JOGO)
            ->get(['a.codigo', 'a.nome', 'a.valor', 'a.premio'])
            ->map(fn (object $linha) => [
                'codigo' => $linha->codigo,
                'apostador' => $linha->nome,
                'valor' => bcadd((string) $linha->valor, '0', 2),
                'premio' => bcadd((string) $linha->premio, '0', 2),
            ])
            ->all();
    }

    /**
     * Os 10 vendedores da rede com mais entradas no dia (inclusive os já excluídos).
     *
     * @return list<array{id: int, nome: string, quantidade: int, entradas: string}>
     */
    public function vendedores(Usuarios $usuario): array
    {
        return $this->apostas_do_balcao($usuario, ...$this->alcance->periodo_do_dia())
            ->join('usuarios as u', 'u.id', '=', 'a.usuarios_id')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->groupBy('u.id', 'u.nome')
            ->select('u.id', 'u.nome')
            ->selectRaw('COUNT(*) as quantidade')
            ->selectRaw('SUM(a.valor) as entradas')
            ->orderByDesc('entradas')
            ->limit(self::LIMITE_RANKING)
            ->get()
            ->map(fn (object $linha) => [
                'id' => (int) $linha->id,
                'nome' => $linha->nome,
                'quantidade' => (int) $linha->quantidade,
                'entradas' => bcadd((string) $linha->entradas, '0', 2),
            ])
            ->all();
    }

    /**
     * Os 10 jogos com mais apostas no dia; cada aposta conta uma vez por jogo (mesmo com vários
     * palpites no jogo) e as entradas somam o valor dessas apostas.
     *
     * @return list<array{confrontos_id: int, confronto: string, quantidade: int, entradas: string}>
     */
    public function jogos_mais_apostados(Usuarios $usuario): array
    {
        $apostas_por_jogo = $this->palpites_do_dia($usuario)
            ->whereNotNull('p.confrontos_id')
            ->select('p.confrontos_id', 'a.id as apostas_id', 'a.valor')
            ->distinct();

        return DB::query()
            ->fromSub($apostas_por_jogo, 'j')
            ->join('confrontos as c', 'c.id', '=', 'j.confrontos_id')
            ->groupBy('c.id', 'c.time_casa', 'c.time_fora')
            ->select('c.id', 'c.time_casa', 'c.time_fora')
            ->selectRaw('COUNT(*) as quantidade')
            ->selectRaw('SUM(j.valor) as entradas')
            ->orderByDesc('quantidade')
            ->orderByDesc('entradas')
            ->limit(self::LIMITE_RANKING)
            ->get()
            ->map(fn (object $linha) => [
                'confrontos_id' => (int) $linha->id,
                'confronto' => "{$linha->time_casa} x {$linha->time_fora}",
                'quantidade' => (int) $linha->quantidade,
                'entradas' => bcadd((string) $linha->entradas, '0', 2),
            ])
            ->all();
    }

    /**
     * As 10 opções (mercados) mais apostadas no dia, pela quantidade de palpites.
     *
     * @return list<array{codigo_cotacao: string, opcao: string, quantidade: int}>
     */
    public function opcoes_mais_apostadas(Usuarios $usuario): array
    {
        return $this->palpites_do_dia($usuario)
            ->whereNotNull('p.confrontos_id')
            ->groupBy('p.codigo_cotacao')
            ->select('p.codigo_cotacao')
            ->selectRaw('COUNT(*) as quantidade')
            ->orderByDesc('quantidade')
            ->limit(self::LIMITE_RANKING)
            ->get()
            ->map(fn (object $linha) => [
                'codigo_cotacao' => $linha->codigo_cotacao,
                'opcao' => NomesCotacoes::nome($linha->codigo_cotacao),
                'quantidade' => (int) $linha->quantidade,
            ])
            ->all();
    }

    /**
     * Apostas do balcão (feitas por vendedor) da rede do usuário no período.
     */
    private function apostas_do_balcao(Usuarios $usuario, mixed $inicio, mixed $fim): Builder
    {
        $consulta = DB::table('apostas as a')
            ->whereNotNull('a.usuarios_id')
            ->whereNull('a.clientes_id')
            ->whereNull('a.deleted_at')
            ->whereBetween('a.created_at', [$inicio, $fim]);

        return $this->alcance->aplicar($consulta, $this->alcance->ids_vendedores($usuario), 'a.usuarios_id');
    }

    /**
     * Palpites ativos das apostas ativas do balcão do dia, na rede do usuário.
     */
    private function palpites_do_dia(Usuarios $usuario): Builder
    {
        return $this->apostas_do_balcao($usuario, ...$this->alcance->periodo_do_dia())
            ->join('apostas_palpites as p', 'p.apostas_id', '=', 'a.id')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->where('p.situacao', SituacaoPalpite::Ativo->value)
            ->whereNull('p.deleted_at');
    }
}
