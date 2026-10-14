<?php

namespace App\Services;

use App\Enums\AcaoHistoricoAposta;
use App\Enums\LiquidacaoCriarAposta;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoBloco;
use App\Enums\SituacaoPalpite;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasBlocos;
use App\Models\ApostasHistorico;
use App\Models\ApostasPalpites;
use App\Models\Usuarios;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Edição da aposta pelo painel (FR-052a a FR-052e): cancelar ou restaurar um palpite, com o
 * prêmio recalculado pelos valores gravados na aposta e a edição registrada no histórico. Valor,
 * saldo, limites, comissão e rollover não mudam.
 */
class EdicaoApostas
{
    private const TAMANHO_USER_AGENT = 255;

    public function __construct(
        private AlcanceApostas $alcance,
        private CalculoPremio $premio,
        private AssinaturaApostas $assinatura,
        private CalculoBlocos $blocos,
    ) {}

    /**
     * @throws RegraApostaException
     */
    public function cancelar_palpite(Usuarios $autor, string $codigo, int $palpites_id, string $ip, ?string $user_agent): Apostas
    {
        return $this->alterar($autor, $codigo, $palpites_id, AcaoHistoricoAposta::CancelarPalpite, $ip, $user_agent);
    }

    /**
     * @throws RegraApostaException
     */
    public function restaurar_palpite(Usuarios $autor, string $codigo, int $palpites_id, string $ip, ?string $user_agent): Apostas
    {
        return $this->alterar($autor, $codigo, $palpites_id, AcaoHistoricoAposta::RestaurarPalpite, $ip, $user_agent);
    }

    private function alterar(Usuarios $autor, string $codigo, int $palpites_id, AcaoHistoricoAposta $acao, string $ip, ?string $user_agent): Apostas
    {
        return DB::transaction(function () use ($autor, $codigo, $palpites_id, $acao, $ip, $user_agent) {
            $aposta = Apostas::buscar_pelo_codigo($codigo)->lockForUpdate()->first();

            abort_unless($aposta !== null, 404, 'Aposta não encontrada.');

            $this->alcance->garantir_pode_editar($autor, $aposta);

            if ($aposta->situacao !== SituacaoAposta::Ativa || $aposta->resultado !== ResultadoAposta::Aguardando) {
                throw new RegraApostaException('Só apostas ativas e ainda não apuradas podem ser editadas.');
            }

            $palpite = ApostasPalpites::where('apostas_id', $aposta->id)->lockForUpdate()->find($palpites_id);

            abort_unless($palpite !== null, 404, 'Palpite não encontrado.');

            $this->marcar($aposta, $palpite, $acao, $autor);
            $this->recalcular_e_registrar($aposta, $palpite, $acao, $autor, $ip, $user_agent);

            return $aposta;
        });
    }

    /**
     * Cancelamento feito pelo sistema ao cancelar uma categoria especial (spec 006, R-11): mesma
     * regra do cancelamento pelo painel, mas pode cancelar o último palpite ativo (sem palpite
     * ativo, a cotação total fica 1,00 e o prêmio igual ao valor, como no sistema antigo). Deve
     * rodar dentro da transação de quem chama, com a aposta e o palpite já bloqueados.
     */
    public function cancelar_palpite_pelo_sistema(Apostas $aposta, ApostasPalpites $palpite, Usuarios $autor, string $ip, ?string $user_agent): Apostas
    {
        $palpite->fill(['situacao' => SituacaoPalpite::Cancelado, 'cancelado_em' => now(), 'cancelado_por' => $autor->id])->save();
        $this->recalcular_e_registrar($aposta, $palpite, AcaoHistoricoAposta::CancelarPalpite, $autor, $ip, $user_agent);

        return $aposta;
    }

    /**
     * Recalcula a aposta e registra a edição no histórico, com os valores de antes e de depois.
     */
    private function recalcular_e_registrar(Apostas $aposta, ApostasPalpites $palpite, AcaoHistoricoAposta $acao, Usuarios $autor, string $ip, ?string $user_agent): void
    {
        $antes = [
            'cotacao_total' => (string) $aposta->cotacao_total,
            'premio' => (string) $aposta->premio,
            'valor_acrescido' => (string) $aposta->valor_acrescido,
        ];

        $this->recalcular($aposta);

        ApostasHistorico::create([
            'apostas_id' => $aposta->id,
            'apostas_palpites_id' => $palpite->id,
            'acao' => $acao,
            'cotacao_total_anterior' => $antes['cotacao_total'],
            'cotacao_total_posterior' => $aposta->cotacao_total,
            'premio_anterior' => $antes['premio'],
            'premio_posterior' => $aposta->premio,
            'valor_acrescido_anterior' => $antes['valor_acrescido'],
            'valor_acrescido_posterior' => $aposta->valor_acrescido,
            'usuarios_id' => $autor->id,
            'ip' => $ip,
            'user_agent' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
        ]);
    }

    /**
     * @throws RegraApostaException
     */
    private function marcar(Apostas $aposta, ApostasPalpites $palpite, AcaoHistoricoAposta $acao, Usuarios $autor): void
    {
        // com "Anular bloco", o palpite de um bloco Criar Aposta leva o bloco inteiro junto (spec 008)
        $bloco = $palpite->apostas_blocos_id !== null && $aposta->liquidacao_criar_aposta === LiquidacaoCriarAposta::AnularBloco
            ? ApostasBlocos::lockForUpdate()->find($palpite->apostas_blocos_id)
            : null;

        if ($acao === AcaoHistoricoAposta::CancelarPalpite) {
            if ($palpite->situacao !== SituacaoPalpite::Ativo) {
                throw new RegraApostaException('O palpite já está cancelado.');
            }

            if ($bloco !== null) {
                $this->anular_bloco($bloco, $autor);

                return;
            }

            if ($aposta->palpites_ativos()->count() === 1) {
                throw new RegraApostaException('Não é possível cancelar o último palpite ativo. Cancele a aposta.');
            }

            $palpite->fill(['situacao' => SituacaoPalpite::Cancelado, 'cancelado_em' => now(), 'cancelado_por' => $autor->id])->save();

            return;
        }

        if ($palpite->situacao !== SituacaoPalpite::Cancelado) {
            throw new RegraApostaException('O palpite não está cancelado.');
        }

        if ($bloco !== null) {
            $this->restaurar_bloco($bloco, $autor);

            return;
        }

        $palpite->fill(['situacao' => SituacaoPalpite::Ativo, 'restaurado_em' => now(), 'restaurado_por' => $autor->id])->save();
    }

    /**
     * "Anular bloco" (spec 008, FR-019): todos os palpites ativos do bloco são cancelados e o bloco
     * fica Anulado, valendo 1,00. Pode deixar a aposta sem palpite ativo: ela continua Ativa com
     * cotação total 1,00 e prêmio igual ao valor.
     */
    private function anular_bloco(ApostasBlocos $bloco, Usuarios $autor): void
    {
        $bloco->palpites()->where('situacao', SituacaoPalpite::Ativo)
            ->update(['situacao' => SituacaoPalpite::Cancelado, 'cancelado_em' => now(), 'cancelado_por' => $autor->id]);

        $bloco->fill(['situacao' => SituacaoBloco::Anulado])->save();
    }

    /**
     * Desfaz a anulação (FR-020): todos os palpites do bloco voltam, com as cotações gravadas.
     */
    private function restaurar_bloco(ApostasBlocos $bloco, Usuarios $autor): void
    {
        $bloco->palpites()->where('situacao', SituacaoPalpite::Cancelado)
            ->update(['situacao' => SituacaoPalpite::Ativo, 'restaurado_em' => now(), 'restaurado_por' => $autor->id]);

        $bloco->fill(['situacao' => SituacaoBloco::Ativo])->save();
    }

    /**
     * Cotação total e prêmio só com os palpites ativos e os valores gravados na aposta; a
     * assinatura é refeita. O bloco Criar Aposta conta como um jogo, recalculado com os palpites
     * ativos e o desconto gravado ("Recalcular", FR-018); o bloco anulado fica de fora (vale 1,00).
     */
    private function recalcular(Apostas $aposta): void
    {
        $ativos = $aposta->palpites_ativos()->orderBy('id')->get();
        $desconto = (string) ($aposta->desconto_criar_aposta ?? '0');

        $this->recalcular_blocos($aposta, $ativos, $desconto);

        $pernas = $this->blocos->pernas($ativos->map(fn (ApostasPalpites $palpite) => [
            'confrontos_id' => $palpite->confrontos_id,
            'confrontos_ao_vivo_id' => $palpite->confrontos_ao_vivo_id,
            'especiais_id' => $palpite->especiais_id,
            'cotacao_final' => (string) $palpite->cotacao_final,
        ])->all(), $desconto, 'cotacao_final');
        $cotacoes = CalculoBlocos::cotacoes($pernas);

        $calculo = $this->premio->calcular(
            (string) $aposta->valor,
            $cotacoes,
            (int) $aposta->multiplicador,
            (string) $aposta->premio_maximo,
            (string) $aposta->ganho_multiplo_palpites,
        );

        $aposta->fill([
            'cotacao_total' => $calculo['cotacao_total'],
            'premio' => $calculo['premio'],
            'valor_acrescido' => $calculo['valor_acrescido'],
        ])->save();

        $aposta->assinatura = $this->assinatura->assinar($aposta);
        $aposta->save();
    }

    /**
     * Cotação gravada de cada bloco ativo, com os palpites que continuam ativos (um só = a cotação
     * dele). Bloco anulado ou sem palpite ativo mantém a última cotação, só para o comprovante.
     *
     * @param  Collection<int, ApostasPalpites>  $ativos
     */
    private function recalcular_blocos(Apostas $aposta, Collection $ativos, string $desconto): void
    {
        foreach ($aposta->blocos()->where('situacao', SituacaoBloco::Ativo)->get() as $bloco) {
            $cotacoes = $ativos->where('apostas_blocos_id', $bloco->id)->map(fn (ApostasPalpites $palpite) => (string) $palpite->cotacao_final)->values()->all();

            if ($cotacoes !== []) {
                $bloco->fill(['cotacao' => $this->blocos->cotacao_bloco($cotacoes, $desconto)])->save();
            }
        }
    }
}
