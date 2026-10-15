<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Events\ConfrontoAtualizado;
use App\Jobs\ConferirEncerramentosAutomaticos;
use App\Models\Campeonatos;
use App\Models\Configuracoes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga das cotações do pré-jogo (rota "cotacao" do provedor): grava as cotações, o sorteio de
 * odd4/odd7 e os jogadores dos confrontos já existentes, numa única transação. Confronto que
 * ainda não existe é ignorado e recebe as cotações na próxima carga, depois da carga dos
 * confrontos. Não consulta porcentagens nem restrições de exibição.
 */
class ImportacaoCotacoes
{
    /**
     * Linhas por comando de gravação em lote (abaixo do limite de 65.535 parâmetros do MySQL).
     */
    private const LOTE = 1000;

    /**
     * Lote da tabela de jogadores, que tem poucas colunas.
     */
    private const LOTE_GRANDE = 2000;

    /**
     * Códigos com sorteio quando o provedor manda zero em confronto de futebol.
     */
    private const CODIGOS_SORTEIO = ['odd4', 'odd7'];

    public function __construct(
        private ProvedorCotacoes $provedor,
        private ValidacaoCargaProvedor $validacao,
    ) {}

    /**
     * @return array<string, int> resumo da carga
     */
    public function importar(): array
    {
        $configuracoes = Configuracoes::atual();

        $resultado = $this->validacao->validar_cotacoes($this->provedor->buscar_cotacoes(Campeonatos::codigos_desativados()));

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga das cotações: {$motivo}");
        }

        // um código repetido na resposta vale uma vez só (a última ocorrência)
        $cotacoes = collect($resultado['cotacoes'])->keyBy('codigo_externo');

        $atuais = $this->confrontos_atuais($cotacoes->keys());
        $ignorados = $cotacoes->count() - count($atuais);

        if ($ignorados > 0) {
            Log::warning("Carga das cotações: {$ignorados} confrontos ignorados, ainda não existem (entram depois da carga dos confrontos).");
        }

        $confrontos = $cotacoes->only(array_keys($atuais))->values();

        $resumo = DB::transaction(fn () => $this->gravar($confrontos, $atuais, $configuracoes, now()) + ['ignorados' => $ignorados]);

        // bilhete em tempo real (spec 010, R-22): as cotações novas chegam aos bilhetes abertos; uma
        // falha aqui não desfaz a carga já gravada
        try {
            $this->avisar_bilhetes($atuais);
        } catch (Throwable $erro) {
            Log::warning('Carga das cotações: aviso aos bilhetes não concluído: '.class_basename($erro).' - '.$erro->getMessage());
        }

        return $resumo;
    }

    /**
     * Avisa o bilhete em tempo real dos jogos da carga que têm palpite em aberto numa aposta ativa
     * (só esses, para não enviar milhares de mensagens a cada carga).
     *
     * @param  array<int, object>  $atuais
     */
    private function avisar_bilhetes(array $atuais): void
    {
        $esportes = collect($atuais)->mapWithKeys(fn (object $atual) => [(int) $atual->id => (string) $atual->esporte]);
        $com_aposta = $this->jogos_com_aposta_aberta($esportes->keys()->all());

        if ($com_aposta === []) {
            return;
        }

        // a oferta muda com as cotações: confere os encerramentos automáticos desses jogos (R-12)
        ConferirEncerramentosAutomaticos::despachar_se_houver($com_aposta);

        $ao_vivo = DB::table('confrontos_ao_vivo')->whereIn('confrontos_id', $com_aposta)->whereNull('deleted_at')->get()->keyBy('confrontos_id');

        foreach ($com_aposta as $confrontos_id) {
            $jogo = $ao_vivo->get($confrontos_id);

            ConfrontoAtualizado::emitir(ConfrontoAtualizado::de_jogo(
                $confrontos_id,
                $jogo,
                $esportes[$confrontos_id],
                $jogo?->situacao,
                $jogo !== null ? (int) $jogo->minuto : null,
                false,
                true,
            ));
        }
    }

    /**
     * Jogos, entre os informados, com palpite ativo e sem resultado numa aposta Ativa e Aguardando.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function jogos_com_aposta_aberta(array $ids): array
    {
        $jogos = [];

        foreach (array_chunk($ids, self::LOTE) as $lote) {
            DB::table('apostas_palpites as ap')
                ->join('apostas as a', 'a.id', '=', 'ap.apostas_id')
                ->whereIn('ap.confrontos_id', $lote)
                ->where('ap.situacao', SituacaoPalpite::Ativo->value)
                ->where('ap.resultado', ResultadoAposta::Aguardando->value)
                ->where('a.situacao', SituacaoAposta::Ativa->value)
                ->where('a.resultado', ResultadoAposta::Aguardando->value)
                ->whereNull('ap.deleted_at')
                ->whereNull('a.deleted_at')
                ->distinct()
                ->pluck('ap.confrontos_id')
                ->each(function (int $id) use (&$jogos) {
                    $jogos[] = $id;
                });
        }

        return $jogos;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $confrontos
     * @param  array<int, object>  $atuais
     * @return array<string, int>
     */
    private function gravar(Collection $confrontos, array $atuais, Configuracoes $configuracoes, Carbon $agora): array
    {
        $confrontos = $this->aplicar_sorteio($confrontos, $atuais, $configuracoes);

        // o confronto já existe: as colunas obrigatórias vão com os valores atuais só para
        // completar a linha, e a atualização mexe apenas nas cotações
        $linhas = $confrontos->map(function (array $confronto) use ($atuais, $agora) {
            $atual = $atuais[$confronto['codigo_externo']];

            return [
                'codigo_externo' => $confronto['codigo_externo'],
                'campeonatos_id' => $atual->campeonatos_id,
                'time_casa' => $atual->time_casa,
                'time_fora' => $atual->time_fora,
                'esporte' => $atual->esporte,
                'data_inicio' => $atual->data_inicio,
                'odd4_sorteada' => $confronto['odd4_sorteada'],
                'odd7_sorteada' => $confronto['odd7_sorteada'],
                'quantidade_cotacoes' => count($confronto['cotacoes']) + count($confronto['jogadores']),
                'cotacoes' => json_encode((object) $confronto['cotacoes']),
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        });

        $atualizadas = ['odd4_sorteada', 'odd7_sorteada', 'quantidade_cotacoes', 'cotacoes', 'updated_at'];

        foreach ($linhas->chunk(self::LOTE) as $lote) {
            DB::table('confrontos')->upsert($lote->values()->all(), ['codigo_externo'], $atualizadas);
        }

        $jogadores = $this->gravar_jogadores($confrontos, $atuais, $agora);

        return [
            'recebidos' => $confrontos->count(),
            'atualizados' => $linhas->count(),
            'jogadores' => $jogadores,
        ];
    }

    /**
     * Confrontos do provedor já gravados (inclusive excluídos), com o necessário para o sorteio e
     * para completar a linha da gravação em lote, por código externo.
     *
     * @param  Collection<int, int>  $codigos
     * @return array<int, object>
     */
    private function confrontos_atuais(Collection $codigos): array
    {
        $atuais = [];

        foreach ($codigos->chunk(self::LOTE_GRANDE) as $lote) {
            DB::table('confrontos')
                ->whereIn('codigo_externo', $lote->values()->all())
                ->where('manual', false)
                ->select(['id', 'codigo_externo', 'campeonatos_id', 'time_casa', 'time_fora', 'esporte', 'data_inicio', 'odd4_sorteada', 'odd7_sorteada'])
                ->selectRaw("JSON_EXTRACT(cotacoes, '$.odd4') as odd4")
                ->selectRaw("JSON_EXTRACT(cotacoes, '$.odd7') as odd7")
                ->get()
                ->each(function (object $linha) use (&$atuais) {
                    $atuais[(int) $linha->codigo_externo] = $linha;
                });
        }

        return $atuais;
    }

    /**
     * Grava só os jogadores novos, alterados ou que estavam excluídos (restaurando-os) e exclui
     * logicamente os jogadores desses confrontos que não vieram na carga.
     *
     * @param  Collection<int, array<string, mixed>>  $confrontos
     * @param  array<int, object>  $atuais
     */
    private function gravar_jogadores(Collection $confrontos, array $atuais, Carbon $agora): int
    {
        $ids_confrontos = $confrontos->mapWithKeys(fn (array $confronto) => [$confronto['codigo_externo'] => $atuais[$confronto['codigo_externo']]->id])->all();
        $jogadores_atuais = $this->jogadores_atuais($ids_confrontos);
        $recebidos = [];
        $linhas = [];

        foreach ($confrontos as $confronto) {
            $confrontos_id = $ids_confrontos[$confronto['codigo_externo']];

            foreach ($confronto['jogadores'] as $jogador) {
                $chave = "{$confrontos_id}|{$jogador['codigo_externo']}|{$jogador['tipo']}";
                $recebidos[$chave] = true;
                $atual = $jogadores_atuais[$chave] ?? null;

                if ($atual !== null && $atual->deleted_at === null && $atual->nome === $jogador['nome']
                    && $atual->opcao === $jogador['opcao'] && (float) $atual->odd === (float) $jogador['odd']) {
                    continue;
                }

                $linhas[] = [
                    'confrontos_id' => $confrontos_id,
                    'codigo_externo' => $jogador['codigo_externo'],
                    'nome' => $jogador['nome'],
                    'opcao' => $jogador['opcao'],
                    'tipo' => $jogador['tipo'],
                    'odd' => $jogador['odd'],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                    'deleted_at' => null,
                ];
            }
        }

        foreach (array_chunk($linhas, self::LOTE_GRANDE) as $lote) {
            DB::table('confrontos_jogadores')->upsert(
                $lote,
                ['confrontos_id', 'codigo_externo', 'tipo'],
                ['nome', 'opcao', 'odd', 'updated_at', 'deleted_at'],
            );
        }

        // jogadores ativos desses confrontos que não vieram mais na carga
        $ausentes = collect($jogadores_atuais)
            ->reject(fn (object $atual, string $chave) => $atual->deleted_at !== null || isset($recebidos[$chave]))
            ->pluck('id');

        foreach ($ausentes->chunk(self::LOTE_GRANDE) as $ids) {
            DB::table('confrontos_jogadores')->whereIn('id', $ids->values()->all())->update(['deleted_at' => $agora, 'updated_at' => $agora]);
        }

        return count($recebidos);
    }

    /**
     * Jogadores já gravados dos confrontos da carga (inclusive excluídos), por
     * "confrontos_id|codigo_externo|tipo".
     *
     * @param  array<int, int>  $ids_confrontos
     * @return array<string, object>
     */
    private function jogadores_atuais(array $ids_confrontos): array
    {
        $atuais = [];

        foreach (array_chunk(array_values($ids_confrontos), self::LOTE_GRANDE) as $ids) {
            DB::table('confrontos_jogadores')
                ->whereIn('confrontos_id', $ids)
                ->get(['id', 'confrontos_id', 'codigo_externo', 'tipo', 'nome', 'opcao', 'odd', 'deleted_at'])
                ->each(function (object $jogador) use (&$atuais) {
                    $atuais["{$jogador->confrontos_id}|{$jogador->codigo_externo}|{$jogador->tipo}"] = $jogador;
                });
        }

        return $atuais;
    }

    /**
     * Sorteio de "ambas marcam" (odd4) e "ambas não marcam" (odd7) quando o provedor manda zero
     * num confronto de futebol: o valor sorteado é mantido nas cargas seguintes enquanto o
     * provedor continuar mandando zero; quando ele mandar um valor, vale o dele.
     *
     * @param  Collection<int, array<string, mixed>>  $confrontos
     * @param  array<int, object>  $atuais
     * @return Collection<int, array<string, mixed>>
     */
    private function aplicar_sorteio(Collection $confrontos, array $atuais, Configuracoes $configuracoes): Collection
    {
        return $confrontos->map(function (array $confronto) use ($atuais, $configuracoes) {
            $atual = $atuais[$confronto['codigo_externo']];
            $futebol = mb_strtoupper($atual->esporte) === 'FUTEBOL';

            foreach (self::CODIGOS_SORTEIO as $codigo) {
                $marcacao = "{$codigo}_sorteada";
                $confronto[$marcacao] = false;

                if (! $futebol || ($confronto['cotacoes'][$codigo] ?? 0) > 0) {
                    continue;
                }

                if ($atual->{$marcacao} && (float) $atual->{$codigo} > 0) {
                    $confronto['cotacoes'][$codigo] = (float) $atual->{$codigo};
                    $confronto[$marcacao] = true;

                    continue;
                }

                $intervalo = $configuracoes->intervalo_sorteio($codigo);

                if ($intervalo !== null) {
                    // sorteio em centésimos, dentro do intervalo configurado
                    $confronto['cotacoes'][$codigo] = random_int((int) round($intervalo[0] * 100), (int) round($intervalo[1] * 100)) / 100;
                    $confronto[$marcacao] = true;
                }
            }

            return $confronto;
        });
    }
}
