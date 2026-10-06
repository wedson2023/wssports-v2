<?php

namespace App\Services;

use App\Models\Campeonatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga do ao vivo: grava placar, minuto, situação e cotações dos jogos em andamento. Só os jogos
 * recebidos válidos têm a data da última atualização renovada; a trava por tempo é calculada na
 * leitura, então resposta vazia ou falha não grava nada e o jogo trava sozinho.
 */
class ImportacaoAoVivo
{
    private const LOTE = 500;

    public function __construct(
        private ProvedorCotacoes $provedor,
        private ValidacaoCargaProvedor $validacao,
    ) {}

    /**
     * @return array<string, int> resumo da carga
     */
    public function importar(): array
    {
        $resultado = $this->validacao->validar_ao_vivo($this->provedor->buscar_ao_vivo(Campeonatos::codigos_desativados()));

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga do ao vivo: {$motivo}");
        }

        $confrontos = collect($resultado['confrontos']);

        if ($confrontos->isEmpty()) {
            return ['recebidos' => 0, 'gravados' => 0];
        }

        $campeonatos = DB::table('campeonatos')
            ->whereIn('codigo_externo', $confrontos->pluck('campeonato_codigo_externo')->unique()->values()->all())
            ->whereNull('deleted_at')
            ->pluck('id', 'codigo_externo');

        // confronto da grade com o mesmo código externo (o ao vivo só exibe jogos que existem no pré-jogo)
        $grade = DB::table('confrontos')
            ->whereIn('codigo_externo', $confrontos->pluck('codigo_externo')->all())
            ->whereNull('deleted_at')
            ->pluck('id', 'codigo_externo');

        $agora = now();
        $linhas = [];

        foreach ($confrontos as $confronto) {
            $campeonatos_id = $campeonatos[$confronto['campeonato_codigo_externo']] ?? null;

            if ($campeonatos_id === null) {
                Log::warning("Carga do ao vivo: jogo {$confronto['codigo_externo']} ignorado, campeonato {$confronto['campeonato_codigo_externo']} ainda não existe.");

                continue;
            }

            $linhas[] = [
                'codigo_externo' => $confronto['codigo_externo'],
                'confrontos_id' => $grade[$confronto['codigo_externo']] ?? null,
                'campeonatos_id' => $campeonatos_id,
                'time_casa' => $confronto['time_casa'],
                'escudo_casa' => $confronto['escudo_casa'],
                'time_fora' => $confronto['time_fora'],
                'escudo_fora' => $confronto['escudo_fora'],
                'esporte' => $confronto['esporte'],
                'data_inicio' => $confronto['data_inicio'],
                'placar_casa' => $confronto['placar_casa'],
                'placar_fora' => $confronto['placar_fora'],
                'gols_primeiro_tempo_casa' => $confronto['gols_primeiro_tempo_casa'],
                'gols_primeiro_tempo_fora' => $confronto['gols_primeiro_tempo_fora'],
                'gols_segundo_tempo_casa' => $confronto['gols_segundo_tempo_casa'],
                'gols_segundo_tempo_fora' => $confronto['gols_segundo_tempo_fora'],
                'escanteios_casa' => $confronto['escanteios_casa'],
                'escanteios_fora' => $confronto['escanteios_fora'],
                'minuto' => $confronto['minuto'],
                'cronometro' => $confronto['cronometro'],
                'situacao' => $confronto['situacao'],
                'cotacoes' => json_encode((object) $confronto['cotacoes']),
                'quantidade_cotacoes' => count($confronto['cotacoes']),
                'ultima_atualizacao_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        $atualizadas = array_values(array_diff(array_keys($linhas[0] ?? []), ['codigo_externo', 'created_at']));

        DB::transaction(function () use ($linhas, $atualizadas) {
            foreach (array_chunk($linhas, self::LOTE) as $lote) {
                DB::table('confrontos_ao_vivo')->upsert($lote, ['codigo_externo'], $atualizadas);
            }
        });

        return ['recebidos' => $confrontos->count(), 'gravados' => count($linhas)];
    }
}
