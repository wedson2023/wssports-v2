<?php

namespace App\Services;

use App\Enums\MotivoCancelamentoPalpite;
use App\Enums\OrigemApuracao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoOperacaoApuracao;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Confrontos;
use App\Models\ConfrontosGols;
use App\Models\ConfrontosResultados;
use App\Support\CodigosCotacao;
use App\Support\PlacarConfronto;
use App\Support\ResultadosCotacao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Apuração das apostas (spec 009, R-09): decide os palpites de jogos pelo resultado gravado, aplica
 * o "empate anula aposta" pelo cancelamento do palpite (e o desfaz se o placar for corrigido),
 * decide a aposta e chama a liquidação. Uma transação e uma trava por aposta.
 */
class ApuracaoApostas
{
    /**
     * Apostas por lote na apuração de um jogo.
     */
    private const LOTE = 500;

    /**
     * Passadas máximas na reavaliação dos palpites: a restauração de um bloco anulado reativa
     * palpites que precisam ser avaliados de novo.
     */
    private const PASSADAS = 2;

    public function __construct(
        private EdicaoApostas $edicao,
        private ResultadosCotacao $regras,
        private RegistroApuracao $registro,
        private LiquidacaoApostas $liquidacao,
    ) {}

    /**
     * Apura todas as apostas Ativas com palpite no jogo; erro numa aposta vai para o log e não
     * interrompe as demais (FR-040).
     *
     * @return array{apuradas: int, alteradas: int, falhas: int}
     */
    public function apurar_confronto(Confrontos $confronto, OrigemApuracao $origem): array
    {
        $resumo = ['apuradas' => 0, 'alteradas' => 0, 'falhas' => 0];

        $ids = DB::table('apostas_palpites')
            ->join('apostas', 'apostas.id', '=', 'apostas_palpites.apostas_id')
            ->where('apostas_palpites.confrontos_id', $confronto->id)
            ->where('apostas.situacao', SituacaoAposta::Ativa->value)
            ->whereNull('apostas_palpites.deleted_at')
            ->whereNull('apostas.deleted_at')
            ->distinct()
            ->orderBy('apostas_palpites.apostas_id')
            ->pluck('apostas_palpites.apostas_id');

        foreach ($ids->chunk(self::LOTE) as $lote) {
            foreach ($lote as $apostas_id) {
                try {
                    $alterou = $this->apurar_aposta((int) $apostas_id, $origem);
                    $resumo['apuradas']++;
                    $resumo['alteradas'] += $alterou ? 1 : 0;
                } catch (Throwable $erro) {
                    $resumo['falhas']++;
                    Log::error("Apuração da aposta {$apostas_id} desfeita: ".class_basename($erro).' - '.$erro->getMessage());
                }
            }
        }

        return $resumo;
    }

    /**
     * @return bool se algo mudou (palpite, resultado ou valor da aposta)
     */
    public function apurar_aposta(int $apostas_id, OrigemApuracao $origem): bool
    {
        return DB::transaction(function () use ($apostas_id, $origem) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);

            if ($aposta === null || $aposta->situacao !== SituacaoAposta::Ativa) {
                return false;
            }

            $antes = $this->fotografia($aposta);
            $alterou = $this->reavaliar_palpites($aposta, $origem);
            $alterou = $this->decidir($aposta, $origem, $antes) || $alterou;

            $this->liquidacao->liquidar($aposta, $origem);

            return $alterou;
        });
    }

    /**
     * Resultado e total a pagar da aposta, para saber depois se a apuração mudou algo.
     *
     * @return array{resultado: string, total_a_pagar: string}
     */
    public function fotografia(Apostas $aposta): array
    {
        return ['resultado' => $aposta->resultado->value, 'total_a_pagar' => $aposta->total_a_pagar()];
    }

    /**
     * Decide a aposta pelos palpites ativos (FR-015): algum Perdedor → Perdedor; algum Aguardando
     * → Aguardando; senão (todos Vencedor ou nenhum ativo) → Vencedor. Quando o resultado ou o
     * total a pagar mudam, grava apurada_em (base da janela de pagamento) e registra. Deve rodar
     * dentro da transação de quem chama, com a aposta bloqueada.
     *
     * @param  array{resultado: string, total_a_pagar: string}  $antes
     */
    public function decidir(Apostas $aposta, OrigemApuracao $origem, array $antes): bool
    {
        // o pluck do Eloquent já aplica o cast do enum
        $resultados = $aposta->palpites_ativos()->pluck('resultado');

        $aposta->resultado = match (true) {
            $resultados->contains(ResultadoAposta::Perdedor) => ResultadoAposta::Perdedor,
            $resultados->contains(fn (ResultadoAposta $resultado) => $resultado !== ResultadoAposta::Vencedor) => ResultadoAposta::Aguardando,
            default => ResultadoAposta::Vencedor,
        };

        $depois = $this->fotografia($aposta);
        $mudou = $depois !== $antes;

        if ($mudou) {
            $aposta->apurada_em = now();

            $this->registro->registrar(
                TipoOperacaoApuracao::ResultadoAposta,
                $origem,
                ['apostas_id' => $aposta->id, 'clientes_id' => $aposta->clientes_id],
                $antes,
                $depois,
                $this->motivo($origem),
            );
        }

        $aposta->liquidacao_pendente = $this->liquidacao->pendente($aposta);
        $aposta->save();

        return $mudou;
    }

    /**
     * Reavalia os palpites de jogos ativos e os devolvidos por empate (FR-013); os especiais
     * mantêm o resultado do encerramento da categoria.
     */
    private function reavaliar_palpites(Apostas $aposta, OrigemApuracao $origem): bool
    {
        $alterou = false;

        for ($passada = 0; $passada < self::PASSADAS; $passada++) {
            $palpites = $aposta->palpites()->with('jogador')->orderBy('id')->get();
            $placares = $this->placares($palpites);
            $restaurou = false;

            foreach ($palpites as $palpite) {
                // cancelar ou restaurar um bloco muda os outros palpites dele
                $palpite->refresh();

                if (! $this->reavaliavel($palpite)) {
                    continue;
                }

                $novo = $this->resultado($palpite, $placares[$palpite->confrontos_id] ?? null);
                $efeito = $this->aplicar($aposta, $palpite, $novo, $origem);

                $alterou = $efeito !== null || $alterou;
                $restaurou = $efeito === TipoOperacaoApuracao::PalpiteRestaurado || $restaurou;
            }

            if (! $restaurou) {
                break;
            }
        }

        return $alterou;
    }

    private function reavaliavel(ApostasPalpites $palpite): bool
    {
        if ($palpite->e_especial() || $palpite->confrontos_id === null) {
            return false;
        }

        return $palpite->situacao === SituacaoPalpite::Ativo
            || ($palpite->motivo_cancelamento === MotivoCancelamentoPalpite::EmpateAnulaAposta && in_array($palpite->codigo_cotacao, ['odd164', 'odd165'], true));
    }

    /**
     * Placar de cada jogo da aposta que já pode ser apurado (Encerrado com placar final).
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @return array<int, PlacarConfronto>
     */
    private function placares(Collection $palpites): array
    {
        $ids = $palpites->pluck('confrontos_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $gols = ConfrontosGols::whereIn('confrontos_id', $ids)->get()->groupBy('confrontos_id');

        return ConfrontosResultados::whereIn('confrontos_id', $ids)->get()
            ->filter(fn (ConfrontosResultados $resultado) => $resultado->apuravel())
            ->mapWithKeys(fn (ConfrontosResultados $resultado) => [
                $resultado->confrontos_id => PlacarConfronto::de($resultado, $gols->get($resultado->confrontos_id, collect())),
            ])
            ->all();
    }

    private function resultado(ApostasPalpites $palpite, ?PlacarConfronto $placar): ResultadoAposta
    {
        if ($placar === null) {
            return ResultadoAposta::Aguardando;
        }

        if ($palpite->codigo_cotacao === CodigosCotacao::JOGADOR) {
            return $this->regras->avaliar_jogador((string) $palpite->jogador?->nome, (string) ($palpite->jogador_tipo ?? $palpite->jogador?->tipo), $placar);
        }

        return $this->regras->avaliar($palpite->codigo_cotacao, $placar);
    }

    /**
     * Grava o novo resultado do palpite. Devolvido cancela o palpite pelo sistema (vale 1,00); o
     * palpite devolvido que deixa de ser empate é restaurado.
     *
     * @return TipoOperacaoApuracao|null a operação feita; null quando nada mudou
     */
    private function aplicar(Apostas $aposta, ApostasPalpites $palpite, ResultadoAposta $novo, OrigemApuracao $origem): ?TipoOperacaoApuracao
    {
        $antes = ['resultado' => $palpite->resultado->value, 'situacao' => $palpite->situacao->value];
        $devolvido = $palpite->situacao === SituacaoPalpite::Cancelado;

        $operacao = match (true) {
            $devolvido && $novo === ResultadoAposta::Devolvido => null,
            $devolvido => TipoOperacaoApuracao::PalpiteRestaurado,
            $novo === ResultadoAposta::Devolvido => TipoOperacaoApuracao::PalpiteCancelado,
            $novo !== $palpite->resultado => TipoOperacaoApuracao::ResultadoPalpite,
            default => null,
        };

        if ($operacao === null) {
            return null;
        }

        if ($operacao === TipoOperacaoApuracao::PalpiteRestaurado) {
            $this->edicao->restaurar_palpite_pelo_sistema($aposta, $palpite);
        }

        $palpite->resultado = $novo;
        $palpite->save();

        if ($operacao === TipoOperacaoApuracao::PalpiteCancelado) {
            $this->edicao->cancelar_palpite_pelo_sistema($aposta, $palpite, null, null, null, MotivoCancelamentoPalpite::EmpateAnulaAposta);
        }

        $this->registro->registrar(
            $operacao,
            $origem,
            [
                'confrontos_id' => $palpite->confrontos_id,
                'apostas_id' => $aposta->id,
                'apostas_palpites_id' => $palpite->id,
                'clientes_id' => $aposta->clientes_id,
            ],
            $antes,
            ['resultado' => $palpite->resultado->value, 'situacao' => $palpite->situacao->value],
            $operacao === TipoOperacaoApuracao::ResultadoPalpite ? $this->motivo($origem) : 'Empate anula aposta',
        );

        return $operacao;
    }

    private function motivo(OrigemApuracao $origem): string
    {
        return match ($origem) {
            OrigemApuracao::CargaResultados => 'Resultado do jogo recebido do provedor',
            OrigemApuracao::CargaCancelados => 'Jogo cancelado pelo provedor',
            OrigemApuracao::LançamentoManual => 'Resultado do jogo lançado à mão',
            OrigemApuracao::ReprocessamentoManual => 'Reapuração manual do jogo',
            OrigemApuracao::EncerramentoEspecial => 'Categoria especial encerrada',
            OrigemApuracao::PagamentoPrêmios => 'Pagamento de prêmios',
        };
    }
}
