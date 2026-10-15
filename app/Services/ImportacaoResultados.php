<?php

namespace App\Services;

use App\Enums\OrigemApuracao;
use App\Enums\SituacaoConfronto;
use App\Enums\TipoEventoConfronto;
use App\Enums\TipoOperacaoApuracao;
use App\Events\ConfrontoAtualizado;
use App\Models\Confrontos;
use App\Models\ConfrontosGols;
use App\Models\ConfrontosResultados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga dos resultados (spec 009, rota "resultados" do provedor) e lançamento à mão do resultado
 * de jogo manual: grava o resultado e os gols e apura as apostas do jogo só quando algo mudou
 * (assinatura SHA-256, R-01).
 */
class ImportacaoResultados
{
    /**
     * Jogos buscados por consulta.
     */
    private const LOTE = 1000;

    /**
     * Colunas do resultado, com os mesmos nomes nos dados validados e na tabela.
     */
    private const CAMPOS = [
        'placar_casa',
        'placar_fora',
        'gols_primeiro_tempo_casa',
        'gols_primeiro_tempo_fora',
        'gols_segundo_tempo_casa',
        'gols_segundo_tempo_fora',
        'escanteios_casa',
        'escanteios_fora',
    ];

    public function __construct(
        private ProvedorCotacoes $provedor,
        private ValidacaoCargaProvedor $validacao,
        private ApuracaoApostas $apuracao,
        private RegistroApuracao $registro,
        private EventosConfrontos $eventos,
    ) {}

    /**
     * @return array<string, int> resumo da carga
     */
    public function importar(OrigemApuracao $origem): array
    {
        $resultado = $this->validacao->validar_resultados($this->provedor->buscar_resultados());

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga dos resultados: {$motivo}");
        }

        // um código repetido na resposta vale uma vez só (a última ocorrência)
        $itens = collect($resultado['resultados'])->keyBy('codigo_externo');
        $resumo = ['recebidos' => $itens->count(), 'alterados' => 0, 'apostas' => 0, 'ignorados' => 0, 'falhas' => 0];

        foreach ($itens->chunk(self::LOTE) as $lote) {
            $confrontos = Confrontos::whereIn('codigo_externo', $lote->keys()->all())->get()->keyBy('codigo_externo');

            foreach ($lote as $codigo => $dados) {
                $confronto = $confrontos->get($codigo);

                if ($confronto === null) {
                    $resumo['ignorados']++;

                    continue;
                }

                try {
                    $gravacao = $this->gravar_resultado($confronto, $dados, $origem);
                    $resumo['alterados'] += $gravacao['alterado'] ? 1 : 0;
                    $resumo['apostas'] += $gravacao['apostas'];
                } catch (Throwable $erro) {
                    $resumo['falhas']++;
                    Log::error("Carga dos resultados: jogo {$codigo} não gravado: ".class_basename($erro).' - '.$erro->getMessage());
                }
            }
        }

        return $resumo;
    }

    /**
     * Grava o resultado de um jogo e apura as apostas dele; o mesmo resultado de novo não muda
     * nada (FR-003, FR-047). `gols` nulo mantém os gols já gravados.
     *
     * @param  array<string, mixed>  $dados  campos de CAMPOS, situacao e gols
     * @return array{alterado: bool, apostas: int}
     */
    public function gravar_resultado(Confrontos $confronto, array $dados, OrigemApuracao $origem): array
    {
        $atual = ConfrontosResultados::where('confrontos_id', $confronto->id)->first();
        $gols = $dados['gols'] ?? $this->gols_gravados($confronto);
        $assinatura = $this->assinatura($dados, $gols);

        if ($atual?->assinatura === $assinatura) {
            return ['alterado' => false, 'apostas' => 0];
        }

        $apuravel_antes = (bool) $atual?->apuravel();

        $novo = DB::transaction(function () use ($confronto, $dados, $gols, $assinatura, $atual, $origem) {
            $antes = $atual !== null ? $this->resumo($atual) : null;

            $resultado = $atual ?? new ConfrontosResultados(['confrontos_id' => $confronto->id]);
            $resultado->fill([
                ...array_intersect_key($dados, array_flip(self::CAMPOS)),
                'situacao' => $dados['situacao'],
                'assinatura' => $assinatura,
                'recebido_em' => now(),
            ])->save();

            if (($dados['gols'] ?? null) !== null) {
                $this->sincronizar_gols($confronto, $gols);
            }

            if ($resultado->situacao === SituacaoConfronto::Encerrado && $confronto->situacao !== SituacaoConfronto::Encerrado) {
                $confronto->forceFill(['situacao' => SituacaoConfronto::Encerrado])->save();
            }

            $this->registro->registrar(
                TipoOperacaoApuracao::ResultadoJogo,
                $origem,
                ['confrontos_id' => $confronto->id],
                $antes,
                [...$this->resumo($resultado), 'gols' => count($gols)],
                $this->motivo($origem, $atual !== null),
            );

            return $resultado;
        });

        // sem placar final antes e depois não há o que reapurar
        if (! $apuravel_antes && ! $novo->apuravel()) {
            return ['alterado' => true, 'apostas' => 0];
        }

        $acabou_de_encerrar = ! $apuravel_antes && $novo->apuravel();

        // o aviso de fim de jogo vai antes da apuração, para os clientes com aposta ainda em aberto
        // nele (spec 010, FR-043)
        if ($acabou_de_encerrar) {
            $this->eventos->registrar_e_avisar($confronto->id, null, TipoEventoConfronto::FimDeJogo, (int) $novo->placar_casa, (int) $novo->placar_fora, null);
        }

        $apuradas = $this->apuracao->apurar_confronto($confronto, $origem)['apuradas'];

        if ($acabou_de_encerrar) {
            $this->avisar_fim_de_jogo($confronto, $novo);
        }

        return ['alterado' => true, 'apostas' => $apuradas];
    }

    /**
     * O jogo acabou de ser encerrado (spec 010): o bilhete em tempo real recebe o placar final, depois
     * da apuração (os selos viram o resultado).
     */
    private function avisar_fim_de_jogo(Confrontos $confronto, ConfrontosResultados $resultado): void
    {
        ConfrontoAtualizado::emitir(ConfrontoAtualizado::de_jogo(
            $confronto->id,
            $resultado->only(self::CAMPOS),
            (string) $confronto->esporte,
            $resultado->situacao->value,
            null,
            false,
            false,
            true,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gols_gravados(Confrontos $confronto): array
    {
        return ConfrontosGols::where('confrontos_id', $confronto->id)->orderBy('ordem')->get()
            ->map(fn (ConfrontosGols $gol) => ['ordem' => $gol->ordem, 'nome' => $gol->nome, 'minuto' => $gol->minuto, 'tempo_jogo' => $gol->tempo_jogo])
            ->all();
    }

    /**
     * Atualiza ou restaura as ordens recebidas e remove (soft delete) as que não vieram.
     *
     * @param  list<array<string, mixed>>  $gols
     */
    private function sincronizar_gols(Confrontos $confronto, array $gols): void
    {
        $existentes = ConfrontosGols::withTrashed()->where('confrontos_id', $confronto->id)->get()->keyBy('ordem');

        foreach ($gols as $gol) {
            $registro = $existentes->get($gol['ordem']) ?? new ConfrontosGols(['confrontos_id' => $confronto->id, 'ordem' => $gol['ordem']]);
            $minuto = $gol['minuto'] ?? null;
            $registro->fill(['nome' => $gol['nome'], 'minuto' => $minuto === null ? null : (string) $minuto, 'tempo_jogo' => $gol['tempo_jogo'] ?? null]);
            $registro->deleted_at = null;
            $registro->save();
        }

        ConfrontosGols::where('confrontos_id', $confronto->id)
            ->whereNotIn('ordem', array_column($gols, 'ordem'))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  list<array<string, mixed>>  $gols
     */
    private function assinatura(array $dados, array $gols): string
    {
        $resultado = array_map(fn (string $campo) => $dados[$campo] ?? null, self::CAMPOS);
        // tipos normalizados: o minuto chega como número no lançamento à mão e como texto do banco
        $gols = array_map(fn (array $gol) => [
            (int) $gol['ordem'],
            $gol['nome'],
            isset($gol['minuto']) ? (string) $gol['minuto'] : null,
            $gol['tempo_jogo'] ?? null,
        ], $gols);

        return hash('sha256', json_encode([$resultado, $dados['situacao'], $gols]));
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(ConfrontosResultados $resultado): array
    {
        return [
            ...$resultado->only(self::CAMPOS),
            'situacao' => $resultado->situacao->value,
        ];
    }

    private function motivo(OrigemApuracao $origem, bool $correcao): string
    {
        if ($origem === OrigemApuracao::LançamentoManual) {
            return $correcao ? 'Resultado corrigido à mão' : 'Resultado lançado à mão';
        }

        return $correcao ? 'Resultado corrigido pelo provedor' : 'Resultado recebido do provedor';
    }
}
