<?php

namespace App\Services;

use App\Enums\SituacaoPalpite;
use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Exposição por opção na gaveta da tela Especiais (spec 024, R-04, contracts/api §2.2): quantas
 * apostas estão em cada opção, quanto foi apostado e quanto a casa pagaria se ela vencesse. Conta só
 * palpites ativos em apostas ativas sem resultado, no alcance de quem consulta, como a lista
 * (ListagemEspeciaisPainel). Simples = aposta com um palpite ativo; múltipla = com mais de um,
 * contada inteira (valor e possível retorno), como na spec 023.
 */
class ExposicaoEspeciais
{
    public function __construct(private readonly ListagemEspeciaisPainel $listagem) {}

    /**
     * @return array{alcance: string, opcoes: list<array<string, mixed>>, total: array{quantidade: int, valor: string}, maior_retorno_opcoes_id: ?int}
     */
    public function por_opcao(Especiais $especial, Usuarios $usuario): array
    {
        $palpites_por_aposta = DB::table('apostas_palpites')
            ->select('apostas_id')
            ->selectRaw('COUNT(*) as palpites')
            ->whereNull('deleted_at')
            ->where('situacao', SituacaoPalpite::Ativo->value)
            ->groupBy('apostas_id');

        $por_aposta = $this->listagem->palpites_em_aberto($usuario)
            ->joinSub($palpites_por_aposta, 'cont', 'cont.apostas_id', '=', 'apostas.id')
            ->where('pal.especiais_id', $especial->id)
            ->select('pal.especiais_opcoes_id', 'apostas.id as apostas_id', 'apostas.valor', 'cont.palpites')
            ->selectRaw(ListagemEspeciaisPainel::RETORNO.' as retorno')
            ->distinct()
            ->toBase();

        $linhas = DB::query()->fromSub($por_aposta, 'x')
            ->select('x.especiais_opcoes_id')
            ->selectRaw(implode(', ', [
                'COUNT(CASE WHEN x.palpites = 1 THEN 1 END) as simples_quantidade',
                'COALESCE(SUM(CASE WHEN x.palpites = 1 THEN x.valor END), 0) as simples_valor',
                'COALESCE(SUM(CASE WHEN x.palpites = 1 THEN x.retorno END), 0) as simples_retorno',
                'COUNT(CASE WHEN x.palpites > 1 THEN 1 END) as multiplas_quantidade',
                'COALESCE(SUM(CASE WHEN x.palpites > 1 THEN x.valor END), 0) as multiplas_valor',
                'COALESCE(SUM(CASE WHEN x.palpites > 1 THEN x.retorno END), 0) as multiplas_retorno',
            ]))
            ->groupBy('x.especiais_opcoes_id')
            ->get()
            ->keyBy('especiais_opcoes_id');

        $opcoes = $especial->opcoes->sortBy('id')->map(function (EspeciaisOpcoes $opcao) use ($linhas) {
            $linha = $linhas->get($opcao->id);
            $simples = self::bloco($linha, 'simples');
            $multiplas = self::bloco($linha, 'multiplas');

            return [
                'id' => $opcao->id,
                'nome' => $opcao->nome,
                'cotacao' => (string) $opcao->cotacao,
                'situacao' => $opcao->situacao_exibida(),
                'empatada' => $opcao->empatada,
                'simples' => $simples,
                'multiplas' => $multiplas,
                'total' => [
                    'quantidade' => $simples['quantidade'] + $multiplas['quantidade'],
                    'valor' => bcadd($simples['valor'], $multiplas['valor'], 2),
                    'possivel_retorno' => bcadd($simples['possivel_retorno'], $multiplas['possivel_retorno'], 2),
                ],
            ];
        });

        // cada aposta tem um palpite só na categoria, então cada linha é uma aposta
        $total = DB::query()->fromSub($por_aposta, 'x')
            ->selectRaw('COUNT(*) as quantidade, COALESCE(SUM(x.valor), 0) as valor')
            ->first();

        $com_retorno = $opcoes->filter(fn (array $opcao) => bccomp($opcao['total']['possivel_retorno'], '0', 2) > 0);

        return [
            'alcance' => $this->listagem->alcance($usuario),
            'opcoes' => $opcoes->values()->all(),
            'total' => ['quantidade' => (int) $total->quantidade, 'valor' => bcadd((string) $total->valor, '0', 2)],
            'maior_retorno_opcoes_id' => $com_retorno->isEmpty()
                ? null
                : $com_retorno->sort(fn (array $a, array $b) => bccomp($b['total']['possivel_retorno'], $a['total']['possivel_retorno'], 2))->first()['id'],
        ];
    }

    /**
     * Quantidade, valor e possível retorno de um grupo (simples ou múltiplas), em texto de 2 casas.
     *
     * @return array{quantidade: int, valor: string, possivel_retorno: string}
     */
    private static function bloco(?object $linha, string $grupo): array
    {
        return [
            'quantidade' => (int) ($linha?->{"{$grupo}_quantidade"} ?? 0),
            'valor' => bcadd((string) ($linha?->{"{$grupo}_valor"} ?? '0'), '0', 2),
            'possivel_retorno' => bcadd((string) ($linha?->{"{$grupo}_retorno"} ?? '0'), '0', 2),
        ];
    }
}
