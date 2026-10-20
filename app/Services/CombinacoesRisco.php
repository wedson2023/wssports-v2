<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Support\FusoSistema;
use App\Support\NomesCotacoes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Combinação de risco (spec 014, FR-027, research R-07): apostas abertas de ontem e hoje do balcão
 * com os mesmos jogos e as mesmas opções em 2 ou mais bilhetes. Uma consulta agregada, sem tabela
 * temporária: a assinatura de cada aposta junta jogo/especial, opção, jogador e opção do especial.
 * Usada pela Dashboard (na rede do usuário) e pelo agendador dos avisos (banca toda).
 */
class CombinacoesRisco
{
    private const LIMITE = 100;

    private const SEPARADOR_ITENS = '|';

    /**
     * Parte da assinatura de um palpite: "<jogo ou e<especial>>:<código>:<jogador>:<opção do especial>".
     */
    private const ITEM_ASSINATURA = "CONCAT(COALESCE(p.confrontos_id, CONCAT('e', p.especiais_id)), ':', p.codigo_cotacao, ':', COALESCE(p.confrontos_jogadores_id, ''), ':', COALESCE(p.especiais_opcoes_id, ''))";

    public function __construct(private readonly AlcanceDashboard $alcance) {}

    /**
     * @param  list<int>|null  $ids_vendedores  alcance da rede; null = banca toda
     * @return list<array{chave: string, jogos: list<string>, opcoes: list<string>, quantidade_bilhetes: int, codigos: list<string>, bilhetes: list<array<string, ?string>>, usuarios_ids: list<int>, valor_apostado: string, premio: string, primeiro_jogo_em: ?string}>
     */
    public function listar(?array $ids_vendedores): array
    {
        [$inicio, $fim] = $this->alcance->periodo_de_ontem_e_hoje();

        // a assinatura e a lista de códigos passam do limite padrão do GROUP_CONCAT (1024)
        DB::statement('SET SESSION group_concat_max_len = 65535');

        $por_aposta = DB::table('apostas as a')
            ->join('apostas_palpites as p', function ($juncao) {
                $juncao->on('p.apostas_id', '=', 'a.id')
                    ->where('p.situacao', SituacaoPalpite::Ativo->value)
                    ->whereNull('p.deleted_at');
            })
            ->leftJoin('confrontos as c', 'c.id', '=', 'p.confrontos_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuarios_id')
            ->whereNotNull('a.usuarios_id')
            ->whereNull('a.clientes_id')
            ->whereNull('a.deleted_at')
            ->where('a.situacao', SituacaoAposta::Ativa->value)
            ->where('a.resultado', ResultadoAposta::Aguardando->value)
            ->whereBetween('a.created_at', [$inicio, $fim])
            ->groupBy('a.id', 'a.codigo', 'a.nome', 'a.valor', 'a.premio', 'a.usuarios_id', 'u.nome')
            ->select('a.id', 'a.codigo', 'a.nome', 'a.valor', 'a.premio', 'a.usuarios_id', 'u.nome as vendedor')
            ->selectRaw('GROUP_CONCAT(DISTINCT '.self::ITEM_ASSINATURA.' ORDER BY '.self::ITEM_ASSINATURA." SEPARATOR '".self::SEPARADOR_ITENS."') as assinatura")
            ->selectRaw('MIN(c.data_inicio) as primeiro_jogo')
            // só apostas com algum jogo (ou especial) ainda em aberto
            ->havingRaw(
                'SUM(CASE WHEN p.confrontos_id IS NOT NULL AND c.situacao IN (?, ?) THEN 1 WHEN p.especiais_id IS NOT NULL AND p.resultado = ? THEN 1 ELSE 0 END) > 0',
                [SituacaoConfronto::Aguardando->value, SituacaoConfronto::Bloqueado->value, ResultadoAposta::Aguardando->value],
            );

        $this->alcance->aplicar($por_aposta, $ids_vendedores, 'a.usuarios_id');

        $linhas = DB::query()
            ->fromSub($por_aposta, 'b')
            ->groupBy('b.assinatura')
            ->select('b.assinatura')
            ->selectRaw('COUNT(*) as quantidade_bilhetes')
            ->selectRaw('SUM(b.valor) as valor_apostado')
            ->selectRaw('SUM(b.premio) as premio')
            ->selectRaw("GROUP_CONCAT(b.codigo ORDER BY b.codigo SEPARATOR ',') as codigos")
            ->selectRaw("GROUP_CONCAT(DISTINCT b.usuarios_id SEPARATOR ',') as usuarios_ids")
            ->selectRaw("JSON_ARRAYAGG(JSON_OBJECT('codigo', b.codigo, 'apostador', b.nome, 'vendedor', b.vendedor, 'valor', b.valor, 'premio', b.premio)) as bilhetes")
            ->selectRaw('MIN(b.primeiro_jogo) as primeiro_jogo')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('premio')
            ->limit(self::LIMITE)
            ->get();

        $nomes = $this->nomes_dos_itens($linhas->pluck('assinatura')->all());

        return $linhas->map(function (object $linha) use ($nomes) {
            $itens = explode(self::SEPARADOR_ITENS, $linha->assinatura);

            return [
                'chave' => sha1($linha->assinatura),
                'jogos' => array_map(fn (string $item) => $nomes[$item]['jogo'], $itens),
                'opcoes' => array_map(fn (string $item) => $nomes[$item]['opcao'], $itens),
                'quantidade_bilhetes' => (int) $linha->quantidade_bilhetes,
                'codigos' => explode(',', $linha->codigos),
                'bilhetes' => $this->bilhetes($linha->bilhetes),
                'usuarios_ids' => array_map('intval', explode(',', $linha->usuarios_ids)),
                'valor_apostado' => bcadd((string) $linha->valor_apostado, '0', 2),
                'premio' => bcadd((string) $linha->premio, '0', 2),
                'primeiro_jogo_em' => $linha->primeiro_jogo
                    ? Carbon::parse($linha->primeiro_jogo, 'UTC')->setTimezone(FusoSistema::FUSO)->toIso8601String()
                    : null,
            ];
        })->all();
    }

    /**
     * Bilhetes da combinação (apostador, vendedor, valor e prêmio), do maior prêmio para o menor,
     * como a lista CLIENTE/VALOR/PRÊMIO do modal do sistema antigo.
     *
     * @return list<array{codigo: string, apostador: string, vendedor: ?string, valor: string, premio: string}>
     */
    private function bilhetes(string $json): array
    {
        $bilhetes = array_map(fn (array $bilhete) => [
            'codigo' => $bilhete['codigo'],
            'apostador' => $bilhete['apostador'],
            'vendedor' => $bilhete['vendedor'],
            'valor' => bcadd((string) $bilhete['valor'], '0', 2),
            'premio' => bcadd((string) $bilhete['premio'], '0', 2),
        ], json_decode($json, true));

        usort($bilhetes, fn (array $a, array $b) => bccomp($b['premio'], $a['premio'], 2));

        return $bilhetes;
    }

    /**
     * Nome do jogo e da opção de cada item das assinaturas devolvidas, com uma consulta por tabela.
     *
     * @param  list<string>  $assinaturas
     * @return array<string, array{jogo: string, opcao: string}>
     */
    private function nomes_dos_itens(array $assinaturas): array
    {
        $itens = array_unique(array_merge(...array_map(fn (string $assinatura) => explode(self::SEPARADOR_ITENS, $assinatura), $assinaturas ?: [''])));
        $itens = array_values(array_filter($itens));
        $partes = array_map(fn (string $item) => explode(':', $item), $itens);

        $ids_confrontos = $ids_especiais = $ids_jogadores = $ids_opcoes = [];

        foreach ($partes as [$alvo, , $jogador, $opcao_especial]) {
            if (str_starts_with($alvo, 'e')) {
                $ids_especiais[] = (int) substr($alvo, 1);
                $ids_opcoes[] = (int) $opcao_especial;
            } else {
                $ids_confrontos[] = (int) $alvo;
            }

            if ($jogador !== '') {
                $ids_jogadores[] = (int) $jogador;
            }
        }

        $confrontos = DB::table('confrontos')->whereIn('id', $ids_confrontos)->get(['id', 'time_casa', 'time_fora'])->keyBy('id');
        $especiais = DB::table('especiais')->whereIn('id', $ids_especiais)->pluck('nome', 'id');
        $opcoes_especiais = DB::table('especiais_opcoes')->whereIn('id', $ids_opcoes)->pluck('nome', 'id');
        $jogadores = DB::table('confrontos_jogadores')->whereIn('id', $ids_jogadores)->pluck('nome', 'id');

        $nomes = [];

        foreach ($itens as $indice => $item) {
            [$alvo, $codigo, $jogador, $opcao_especial] = $partes[$indice];

            if (str_starts_with($alvo, 'e')) {
                $nomes[$item] = [
                    'jogo' => $especiais[(int) substr($alvo, 1)] ?? 'Especial',
                    'opcao' => $opcoes_especiais[(int) $opcao_especial] ?? $codigo,
                ];

                continue;
            }

            $confronto = $confrontos[(int) $alvo] ?? null;
            $opcao = NomesCotacoes::nome($codigo);

            $nomes[$item] = [
                'jogo' => $confronto ? "{$confronto->time_casa} x {$confronto->time_fora}" : 'Jogo',
                'opcao' => $jogador !== '' && isset($jogadores[(int) $jogador]) ? "{$opcao} ({$jogadores[(int) $jogador]})" : $opcao,
            ];
        }

        return $nomes;
    }
}
