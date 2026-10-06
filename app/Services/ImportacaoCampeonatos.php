<?php

namespace App\Services;

use App\Models\Configuracoes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga dos campeonatos do pré-jogo (rota "campeonatos" do provedor): grava só os novos ou
 * alterados, numa única transação, e passa as regras de um campeonato antigo para o novo com o
 * mesmo nome e país. Não altera ativo nem favorito de campeonatos existentes.
 */
class ImportacaoCampeonatos
{
    /**
     * Linhas por comando de gravação em lote (abaixo do limite de 65.535 parâmetros do MySQL).
     */
    private const LOTE = 2000;

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

        $resultado = $this->validacao->validar_campeonatos($this->provedor->buscar_campeonatos());

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga dos campeonatos: {$motivo}");
        }

        // um código repetido na resposta vale uma vez só (a última ocorrência)
        $campeonatos = collect($resultado['campeonatos'])->keyBy('codigo_externo')->values();

        return DB::transaction(fn () => $this->gravar($campeonatos, $configuracoes, now()));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campeonatos
     * @return array<string, int>
     */
    private function gravar(Collection $campeonatos, Configuracoes $configuracoes, Carbon $agora): array
    {
        $atuais = DB::table('campeonatos')
            ->whereNotNull('codigo_externo')
            ->get(['codigo_externo', 'nome', 'pais', 'bandeira'])
            ->keyBy('codigo_externo');

        $herancas = $this->campeonatos_herdados($campeonatos->reject(fn (array $campeonato) => isset($atuais[$campeonato['codigo_externo']])));

        // só grava campeonatos novos ou com nome, país ou bandeira alterados
        $alterados = $campeonatos->reject(function (array $campeonato) use ($atuais) {
            $atual = $atuais[$campeonato['codigo_externo']] ?? null;

            return $atual !== null && $atual->nome === $campeonato['nome']
                && $atual->pais === $campeonato['pais'] && $atual->bandeira === $campeonato['bandeira'];
        });

        $linhas = $alterados->map(fn (array $campeonato) => [
            'codigo_externo' => $campeonato['codigo_externo'],
            'nome' => $campeonato['nome'],
            'pais' => $campeonato['pais'],
            'bandeira' => $campeonato['bandeira'],
            // só vale na criação: ativo e favorito de campeonatos existentes não mudam
            'ativo' => $configuracoes->permitir_entrada_campeonatos,
            'favorito' => false,
            'manual' => false,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        foreach ($linhas->chunk(self::LOTE) as $lote) {
            DB::table('campeonatos')->upsert($lote->values()->all(), ['codigo_externo'], ['nome', 'pais', 'bandeira', 'updated_at']);
        }

        $this->transferir_regras($herancas);

        return [
            'recebidos' => $campeonatos->count(),
            'gravados' => $linhas->count(),
            'herancas' => count($herancas),
        ];
    }

    /**
     * Campeonatos que chegam com código novo e o mesmo nome e país de um campeonato do provedor
     * já existente: as regras do antigo passam para o novo. Devolve [código novo => id antigo].
     *
     * @param  Collection<int, array<string, mixed>>  $novos
     * @return array<int, int>
     */
    private function campeonatos_herdados(Collection $novos): array
    {
        if ($novos->isEmpty()) {
            return [];
        }

        $antigos = DB::table('campeonatos')
            ->where('manual', false)
            ->whereNull('deleted_at')
            ->whereIn('nome', $novos->pluck('nome')->unique()->values()->all())
            ->orderBy('id')
            ->get(['id', 'nome', 'pais'])
            ->keyBy(fn (object $campeonato) => mb_strtolower($campeonato->nome.'|'.$campeonato->pais));

        $herancas = [];

        foreach ($novos as $novo) {
            $antigo = $antigos[mb_strtolower($novo['nome'].'|'.$novo['pais'])] ?? null;

            if ($antigo !== null) {
                $herancas[$novo['codigo_externo']] = $antigo->id;
            }
        }

        return $herancas;
    }

    /**
     * @param  array<int, int>  $herancas
     */
    private function transferir_regras(array $herancas): void
    {
        if ($herancas === []) {
            return;
        }

        $ids_novos = DB::table('campeonatos')->whereIn('codigo_externo', array_keys($herancas))->pluck('id', 'codigo_externo');

        foreach ($herancas as $codigo_novo => $id_antigo) {
            $id_novo = $ids_novos[$codigo_novo];

            foreach (['porcentagens_campeonatos', 'campeonatos_nao_permitidos'] as $tabela) {
                DB::table($tabela)->where('campeonatos_id', $id_antigo)->update(['campeonatos_id' => $id_novo]);
            }

            Log::info("Carga dos campeonatos: regras do campeonato {$id_antigo} passaram para o campeonato {$id_novo} (mesmo nome e país).");
        }
    }
}
