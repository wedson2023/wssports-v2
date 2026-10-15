<?php

namespace App\Services;

use App\Enums\MotivoCancelamentoPalpite;
use App\Enums\OrigemApuracao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoOperacaoApuracao;
use App\Models\Apostas;
use App\Models\Configuracoes;
use App\Models\Confrontos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carga dos jogos cancelados ou adiados (spec 009, R-15; substitui fonte:cancelados): marca o jogo
 * e, com a remoção automática ligada, cancela pelo sistema os palpites dele nas apostas ainda sem
 * resultado, só na aposta afetada (o antigo apagava o palpite em todos os bilhetes). Sem palpite
 * restante, a aposta fica Vencedor com prêmio igual ao valor e a liquidação devolve o valor.
 */
class CancelamentoConfrontos
{
    /**
     * Jogos buscados por consulta.
     */
    private const LOTE_JOGOS = 1000;

    /**
     * Apostas por lote.
     */
    private const LOTE_APOSTAS = 500;

    public function __construct(
        private ProvedorCotacoes $provedor,
        private ValidacaoCargaProvedor $validacao,
        private EdicaoApostas $edicao,
        private ApuracaoApostas $apuracao,
        private LiquidacaoApostas $liquidacao,
        private RegistroApuracao $registro,
    ) {}

    /**
     * @return array<string, int> resumo da carga
     */
    public function importar(OrigemApuracao $origem): array
    {
        $resultado = $this->validacao->validar_cancelados($this->provedor->buscar_cancelados());

        foreach ($resultado['motivos'] as $motivo) {
            Log::warning("Carga dos cancelados: {$motivo}");
        }

        $remover = Configuracoes::atual()->remover_confronto_cancelado;
        $resumo = ['recebidos' => count($resultado['codigos']), 'cancelados' => 0, 'palpites' => 0, 'ignorados' => 0, 'falhas' => 0];

        foreach (array_chunk($resultado['codigos'], self::LOTE_JOGOS) as $codigos) {
            $confrontos = Confrontos::whereIn('codigo_externo', $codigos)->get();
            $resumo['ignorados'] += count($codigos) - $confrontos->count();

            foreach ($confrontos as $confronto) {
                try {
                    $resumo['cancelados'] += $this->marcar_cancelado($confronto, $origem) ? 1 : 0;
                } catch (Throwable $erro) {
                    $resumo['falhas']++;
                    Log::error("Carga dos cancelados: jogo {$confronto->codigo_externo} não marcado: ".class_basename($erro).' - '.$erro->getMessage());

                    continue;
                }

                if ($remover) {
                    $this->cancelar_palpites($confronto, $origem, $resumo);
                }
            }
        }

        return $resumo;
    }

    /**
     * Grava Cancelado no jogo que ainda não está Cancelado nem Adiado (a rota não diz qual dos dois;
     * os dois têm o mesmo tratamento).
     */
    private function marcar_cancelado(Confrontos $confronto, OrigemApuracao $origem): bool
    {
        if (in_array($confronto->situacao, [SituacaoConfronto::Cancelado, SituacaoConfronto::Adiado], true)) {
            return false;
        }

        DB::transaction(function () use ($confronto, $origem) {
            $antes = $confronto->situacao->value;
            $confronto->forceFill(['situacao' => SituacaoConfronto::Cancelado])->save();

            $this->registro->registrar(
                TipoOperacaoApuracao::SituaçãoJogo,
                $origem,
                ['confrontos_id' => $confronto->id],
                ['situacao' => $antes],
                ['situacao' => SituacaoConfronto::Cancelado->value],
                'Jogo cancelado ou adiado pelo provedor',
            );
        });

        return true;
    }

    /**
     * Cancela os palpites do jogo nas apostas Ativas ainda Aguardando (FR-032), uma transação por
     * aposta; erro numa aposta vai para o log e não interrompe as demais.
     *
     * @param  array<string, int>  $resumo
     */
    private function cancelar_palpites(Confrontos $confronto, OrigemApuracao $origem, array &$resumo): void
    {
        $ids = DB::table('apostas_palpites')
            ->join('apostas', 'apostas.id', '=', 'apostas_palpites.apostas_id')
            ->where('apostas_palpites.confrontos_id', $confronto->id)
            ->where('apostas_palpites.situacao', SituacaoPalpite::Ativo->value)
            ->where('apostas_palpites.resultado', ResultadoAposta::Aguardando->value)
            ->where('apostas.situacao', SituacaoAposta::Ativa->value)
            ->where('apostas.resultado', ResultadoAposta::Aguardando->value)
            ->whereNull('apostas_palpites.deleted_at')
            ->whereNull('apostas.deleted_at')
            ->distinct()
            ->orderBy('apostas_palpites.apostas_id')
            ->pluck('apostas_palpites.apostas_id');

        foreach ($ids->chunk(self::LOTE_APOSTAS) as $lote) {
            foreach ($lote as $apostas_id) {
                try {
                    $resumo['palpites'] += $this->cancelar_na_aposta((int) $apostas_id, $confronto, $origem);
                } catch (Throwable $erro) {
                    $resumo['falhas']++;
                    Log::error("Cancelamento do jogo {$confronto->codigo_externo} na aposta {$apostas_id} desfeito: ".class_basename($erro).' - '.$erro->getMessage());
                }
            }
        }
    }

    /**
     * @return int palpites cancelados na aposta
     */
    private function cancelar_na_aposta(int $apostas_id, Confrontos $confronto, OrigemApuracao $origem): int
    {
        return DB::transaction(function () use ($apostas_id, $confronto, $origem) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);

            if ($aposta === null || $aposta->situacao !== SituacaoAposta::Ativa || $aposta->resultado !== ResultadoAposta::Aguardando) {
                return 0;
            }

            $antes = $this->apuracao->fotografia($aposta);
            $palpites = $aposta->palpites()->where('confrontos_id', $confronto->id)->orderBy('id')->lockForUpdate()->get();
            $cancelados = 0;

            foreach ($palpites as $palpite) {
                // com "Anular bloco", o primeiro palpite já cancela os outros do mesmo bloco
                $palpite->refresh();

                if ($palpite->situacao !== SituacaoPalpite::Ativo || $palpite->resultado !== ResultadoAposta::Aguardando) {
                    continue;
                }

                $this->edicao->cancelar_palpite_pelo_sistema($aposta, $palpite, null, null, null, MotivoCancelamentoPalpite::JogoCancelado);
                $cancelados++;

                $this->registro->registrar(
                    TipoOperacaoApuracao::PalpiteCancelado,
                    $origem,
                    [
                        'confrontos_id' => $confronto->id,
                        'apostas_id' => $aposta->id,
                        'apostas_palpites_id' => $palpite->id,
                        'clientes_id' => $aposta->clientes_id,
                    ],
                    ['situacao' => SituacaoPalpite::Ativo->value],
                    ['situacao' => SituacaoPalpite::Cancelado->value],
                    'Jogo cancelado pelo provedor',
                );
            }

            $this->apuracao->decidir($aposta, $origem, $antes);
            $this->liquidacao->liquidar($aposta, $origem);

            return $cancelados;
        });
    }
}
