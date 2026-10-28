<?php

namespace App\Services;

use App\Enums\Funcao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEspecial;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\Especiais;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Lista, lixeira e cartões da tela Especiais do painel (spec 024, R-02 e R-03). Os números de
 * apostas contam só palpites especiais ativos em apostas ativas sem resultado, no alcance da tela
 * Apostas (ListagemApostas::aplicar_alcance); as categorias são as mesmas para todos.
 */
class ListagemEspeciaisPainel
{
    /**
     * Possível retorno de uma aposta (Apostas::total_a_pagar): prêmio + acréscimo, só da parte que
     * continua valendo depois de encerramentos parciais, truncado em centavos.
     */
    public const RETORNO = 'TRUNCATE((apostas.premio + apostas.valor_acrescido) * COALESCE(apostas.fracao_restante, 1), 2)';

    public function __construct(private readonly ListagemApostas $listagem_apostas) {}

    /**
     * Categorias do filtro com as opções, a quantidade de opções ativas e os números em aberto.
     *
     * @param  array{situacao: string, busca: ?string, lixeira: bool}  $filtros
     */
    public function consulta(Usuarios $usuario, array $filtros): Builder
    {
        $consulta = ($filtros['lixeira'] ? Especiais::onlyTrashed() : Especiais::query())
            ->leftJoinSub($this->em_aberto_por_categoria($usuario), 'aberto', 'aberto.especiais_id', '=', 'especiais.id')
            ->select('especiais.*')
            ->selectRaw('COALESCE(aberto.apostas_em_aberto, 0) as apostas_em_aberto, COALESCE(aberto.valor_em_aberto, 0) as valor_em_aberto')
            ->withCount(['opcoes as quantidade_opcoes' => fn (Builder $opcoes) => $opcoes->where('ativo', true)->whereNull('anulada_em')])
            ->with('opcoes');

        $this->aplicar_busca($consulta, $filtros['busca']);

        if ($filtros['lixeira']) {
            return $consulta->orderByDesc('especiais.deleted_at')->orderByDesc('especiais.id');
        }

        return match ($filtros['situacao']) {
            SituacaoEspecial::Aguardando->value => $consulta->where('especiais.situacao', SituacaoEspecial::Aguardando->value)
                ->orderBy('especiais.data_limite')->orderBy('especiais.nome'),
            // Todas: as Aguardando primeiro (pela data limite), depois as demais pelo encerramento
            'Todas' => $consulta
                ->orderByRaw('CASE WHEN especiais.situacao = ? THEN 0 ELSE 1 END', [SituacaoEspecial::Aguardando->value])
                ->orderByRaw('CASE WHEN especiais.situacao = ? THEN especiais.data_limite END', [SituacaoEspecial::Aguardando->value])
                ->orderByDesc('especiais.encerrado_em')->orderBy('especiais.nome'),
            default => $consulta->where('especiais.situacao', $filtros['situacao'])
                ->orderByDesc('especiais.encerrado_em')->orderBy('especiais.nome'),
        };
    }

    /**
     * Cartões da tela (contracts/api §2.1), respeitando a busca.
     *
     * @return array{aceitando_palpites: int, aguardando_resultado: int, valor_em_aberto: string, possivel_retorno_em_aberto: string, alcance: string}
     */
    public function resumo(Usuarios $usuario, ?string $busca): array
    {
        $categorias = Especiais::query()->where('situacao', SituacaoEspecial::Aguardando->value);
        $this->aplicar_busca($categorias, $busca);

        $ids = Especiais::query()->select('id');
        $this->aplicar_busca($ids, $busca);

        // uma aposta com palpites em duas categorias conta uma vez só
        $por_aposta = $this->palpites_em_aberto($usuario)
            ->whereIn('pal.especiais_id', $ids->toBase())
            ->select('apostas.id', 'apostas.valor')
            ->selectRaw(self::RETORNO.' as retorno')
            ->distinct()
            ->toBase();

        $totais = DB::query()->fromSub($por_aposta, 'x')
            ->selectRaw('COALESCE(SUM(x.valor), 0) as valor, COALESCE(SUM(x.retorno), 0) as retorno')
            ->first();

        return [
            'aceitando_palpites' => (clone $categorias)->where('ativo', true)->where('data_limite', '>', now())->count(),
            'aguardando_resultado' => (clone $categorias)->where('data_limite', '<=', now())->count(),
            'valor_em_aberto' => bcadd((string) $totais->valor, '0', 2),
            'possivel_retorno_em_aberto' => bcadd((string) $totais->retorno, '0', 2),
            'alcance' => $this->alcance($usuario),
        ];
    }

    /**
     * "todas" para quem vê as apostas da banca toda (Admin); "rede" para quem vê só a da rede, e a
     * tela mostra "Números da sua rede".
     */
    public function alcance(Usuarios $usuario): string
    {
        return $usuario->funcao() === Funcao::Admin ? 'todas' : 'rede';
    }

    /**
     * Palpites especiais ativos em apostas ativas ainda sem resultado (alias apostas e pal), no
     * alcance de quem consulta. Também usado pela exposição da gaveta (ExposicaoEspeciais).
     */
    public function palpites_em_aberto(Usuarios $usuario): Builder
    {
        $consulta = Apostas::query()
            ->join('apostas_palpites as pal', 'pal.apostas_id', '=', 'apostas.id')
            ->whereNull('pal.deleted_at')
            ->whereNotNull('pal.especiais_id')
            ->where('pal.situacao', SituacaoPalpite::Ativo->value)
            ->where('apostas.situacao', SituacaoAposta::Ativa->value)
            ->where('apostas.resultado', ResultadoAposta::Aguardando->value);

        $this->listagem_apostas->aplicar_alcance($consulta, $usuario, null);

        return $consulta;
    }

    /**
     * Apostas e valor em aberto por categoria.
     */
    private function em_aberto_por_categoria(Usuarios $usuario): QueryBuilder
    {
        $por_aposta = $this->palpites_em_aberto($usuario)
            ->select('pal.especiais_id', 'apostas.id as apostas_id', 'apostas.valor')
            ->distinct()
            ->toBase();

        return DB::query()->fromSub($por_aposta, 'x')
            ->select('x.especiais_id')
            ->selectRaw('COUNT(*) as apostas_em_aberto, SUM(x.valor) as valor_em_aberto')
            ->groupBy('x.especiais_id');
    }

    private function aplicar_busca(Builder $consulta, ?string $busca): void
    {
        if ($busca !== null) {
            $consulta->where('especiais.nome', 'like', '%'.addcslashes($busca, '%_\\').'%');
        }
    }
}
