<?php

namespace App\Services;

use App\Enums\MotivoCancelamentoPalpite;
use App\Enums\OrigemApuracao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoConfronto;
use App\Enums\SituacaoPalpite;
use App\Enums\TipoNotificacao;
use App\Enums\TipoOperacaoApuracao;
use App\Events\ApostaAtualizada;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Confrontos;
use App\Models\ConfrontosGols;
use App\Models\ConfrontosResultados;
use App\Support\CodigosCotacao;
use App\Support\PlacarConfronto;
use App\Support\ResultadosCotacao;
use Illuminate\Support\Carbon;
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
        private EncerramentoApostas $encerramento,
        private EncerramentosAutomaticos $automaticos,
        private NotificacoesPush $notificacoes,
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
        $codigo = null;

        $alterou = DB::transaction(function () use ($apostas_id, $origem, &$codigo) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);

            if ($aposta === null || $aposta->situacao !== SituacaoAposta::Ativa) {
                return false;
            }

            $codigo = $aposta->codigo;
            $antes = $this->fotografia($aposta);
            $alterou = $this->reavaliar_palpites($aposta, $origem);
            $alterou = $this->conferir_encerramentos($aposta, $origem) || $alterou;
            $alterou = $this->decidir($aposta, $origem, $antes) || $alterou;

            $this->liquidacao->liquidar($aposta, $origem);

            return $alterou;
        });

        // bilhete em tempo real (spec 010): só depois de gravado
        if ($alterou) {
            ApostaAtualizada::emitir($codigo, 'apuracao');
        }

        return $alterou;
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
        // a aposta encerrada pelo cliente (spec 010) mantém o resultado: os palpites seguem apurados
        // só para o histórico e ela não recebe prêmio (FR-034)
        if ($aposta->resultado !== ResultadoAposta::Encerrada) {
            // o pluck do Eloquent já aplica o cast do enum
            $resultados = $aposta->palpites_ativos()->pluck('resultado');

            $aposta->resultado = match (true) {
                $resultados->contains(ResultadoAposta::Perdedor) => ResultadoAposta::Perdedor,
                $resultados->contains(fn (ResultadoAposta $resultado) => $resultado !== ResultadoAposta::Vencedor) => ResultadoAposta::Aguardando,
                default => ResultadoAposta::Vencedor,
            };
        }

        $depois = $this->fotografia($aposta);
        $mudou = $depois !== $antes;

        if ($mudou && in_array($aposta->resultado, [ResultadoAposta::Vencedor, ResultadoAposta::Perdedor], true)) {
            $this->aposta_decidida($aposta, $antes['resultado']);
        }

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
     * Aposta decidida (spec 010): o encerramento automático dela deixa de valer (R-12) e o cliente é
     * avisado do resultado quando ela sai de Aguardando (FR-044). Correções posteriores não geram
     * novo aviso do mesmo resultado (chave com o resultado).
     */
    private function aposta_decidida(Apostas $aposta, string $resultado_antes): void
    {
        $this->automaticos->cancelar_da_aposta($aposta, 'Aposta decidida');

        if ($aposta->clientes_id === null || $resultado_antes !== ResultadoAposta::Aguardando->value) {
            return;
        }

        $ganhou = $aposta->resultado === ResultadoAposta::Vencedor;

        $this->notificacoes->avisar(
            $aposta->clientes_id,
            TipoNotificacao::ResultadoAposta,
            "aposta:{$aposta->id}:{$aposta->resultado->value}",
            $ganhou ? "Você ganhou! Aposta {$aposta->codigo}" : "Aposta {$aposta->codigo} perdida",
            $ganhou ? 'Prêmio de '.RegrasAposta::reais($aposta->total_a_pagar()) : null,
            "/bilhete/{$aposta->codigo}",
            apostas_id: $aposta->id,
        );
    }

    /**
     * Encerramento desfeito (spec 010, FR-033, R-11): um palpite que estava em aberto no momento do
     * encerramento ficou Perdedor e o jogo dele já estava decidido naquele momento (resultado
     * Encerrado registrado antes, ou ao vivo travado/com resultado na fotografia do encerramento).
     * Deve rodar dentro da transação de quem chama, com a aposta bloqueada.
     *
     * @return bool se algum encerramento foi desfeito
     */
    private function conferir_encerramentos(Apostas $aposta, OrigemApuracao $origem): bool
    {
        $encerramentos = $aposta->encerramentos()->queValem()->orderBy('id')->get();

        if ($encerramentos->isEmpty()) {
            return false;
        }

        $perdedores = $aposta->palpites_ativos()->where('resultado', ResultadoAposta::Perdedor)->get()->keyBy('id');
        $desfez = false;

        foreach ($encerramentos as $encerramento) {
            foreach ($encerramento->palpites ?? [] as $fotografia) {
                $palpite = $perdedores->get($fotografia['id'] ?? 0);

                if ($palpite !== null && $this->jogo_decidido_antes($palpite, $fotografia, $encerramento->decidido_em)) {
                    $this->encerramento->desfazer($encerramento, $aposta, $origem, "Jogo {$fotografia['jogo']} já decidido antes do encerramento");
                    $desfez = true;
                    break;
                }
            }
        }

        return $desfez;
    }

    /**
     * O jogo do palpite já estava decidido quando o encerramento foi feito.
     *
     * @param  array<string, mixed>  $fotografia  palpite na fotografia do encerramento
     */
    private function jogo_decidido_antes(ApostasPalpites $palpite, array $fotografia, ?Carbon $momento): bool
    {
        if (($fotografia['jogo_com_resultado'] ?? false) || ($fotografia['travado'] ?? false)) {
            return true;
        }

        return $momento !== null && DB::table('operacoes_apuracao')
            ->where('tipo', TipoOperacaoApuracao::ResultadoJogo->value)
            ->where('confrontos_id', $palpite->confrontos_id)
            ->where('depois->situacao', SituacaoConfronto::Encerrado->value)
            ->where('created_at', '<', $momento)
            ->whereNull('deleted_at')
            ->exists();
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
            OrigemApuracao::CorreçãoEspecial => 'Resultado da categoria especial corrigido',
            OrigemApuracao::PagamentoPrêmios => 'Pagamento de prêmios',
        };
    }
}
