<?php

namespace App\Services;

use App\Models\Campeonatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga dos confrontos do pré-jogo (rota "confrontos" do provedor): times, escudos, esporte,
 * situação e horário, gravados por inserir-ou-atualizar em lote numa única transação. As
 * cotações e os jogadores chegam pela carga das cotações; ativo e manual nunca são alterados.
 */
class ImportacaoConfrontos
{
    /**
     * Linhas por comando de gravação em lote (abaixo do limite de 65.535 parâmetros do MySQL).
     */
    private const LOTE = 1000;

    public function __construct(
        private ProvedorCotacoes $provedor,
        private ValidacaoCargaProvedor $validacao,
    ) {}

    /**
     * @return array<string, int> resumo da carga
     */
    public function importar(): array
    {
        $resultado = $this->validacao->validar_confrontos($this->provedor->buscar_confrontos(Campeonatos::codigos_desativados()));

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga dos confrontos: {$motivo}");
        }

        // um código repetido na resposta vale uma vez só (a última ocorrência)
        $confrontos = collect($resultado['confrontos'])->keyBy('codigo_externo')->values();

        $campeonatos = [];

        foreach ($confrontos->pluck('campeonato_codigo_externo')->unique()->chunk(self::LOTE) as $codigos) {
            DB::table('campeonatos')
                ->whereIn('codigo_externo', $codigos->values()->all())
                ->whereNull('deleted_at')
                ->pluck('id', 'codigo_externo')
                ->each(function (int $id, int $codigo) use (&$campeonatos) {
                    $campeonatos[$codigo] = $id;
                });
        }

        $agora = now();
        $linhas = [];
        $sem_campeonato = [];

        foreach ($confrontos as $confronto) {
            $campeonatos_id = $campeonatos[$confronto['campeonato_codigo_externo']] ?? null;

            if ($campeonatos_id === null) {
                $sem_campeonato[$confronto['campeonato_codigo_externo']] = true;

                continue;
            }

            $linhas[] = [
                'codigo_externo' => $confronto['codigo_externo'],
                'campeonatos_id' => $campeonatos_id,
                'time_casa' => $confronto['time_casa'],
                'escudo_casa' => $confronto['escudo_casa'],
                'time_fora' => $confronto['time_fora'],
                'escudo_fora' => $confronto['escudo_fora'],
                'esporte' => $confronto['esporte'],
                'situacao' => $confronto['situacao'],
                'data_inicio' => $confronto['data_inicio'],
                // valores de criação: cotações e quantidade chegam pela carga das cotações
                'ativo' => true,
                'manual' => false,
                'odd4_sorteada' => false,
                'odd7_sorteada' => false,
                'quantidade_cotacoes' => 0,
                'cotacoes' => '{}',
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        if ($sem_campeonato !== []) {
            // o campeonato entra na próxima carga dos campeonatos, e os jogos dele na carga seguinte
            Log::warning('Carga dos confrontos: '.(count($confrontos) - count($linhas)).' confrontos ignorados, campeonatos ainda não existem: '
                .implode(', ', array_slice(array_keys($sem_campeonato), 0, 50)).'.');
        }

        // ativo, manual, cotações e sorteio não entram na atualização
        $atualizadas = [
            'campeonatos_id', 'time_casa', 'escudo_casa', 'time_fora', 'escudo_fora', 'esporte', 'situacao',
            'data_inicio', 'updated_at',
        ];

        DB::transaction(function () use ($linhas, $atualizadas) {
            foreach (array_chunk($linhas, self::LOTE) as $lote) {
                DB::table('confrontos')->upsert($lote, ['codigo_externo'], $atualizadas);
            }
        });

        return [
            'recebidos' => $confrontos->count(),
            'gravados' => count($linhas),
            'ignorados' => $confrontos->count() - count($linhas),
        ];
    }
}
