<?php

namespace App\Services;

use App\Enums\AcaoHistoricoAposta;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasHistorico;
use App\Models\ApostasPalpites;
use App\Models\Usuarios;
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

            return $aposta;
        });
    }

    /**
     * @throws RegraApostaException
     */
    private function marcar(Apostas $aposta, ApostasPalpites $palpite, AcaoHistoricoAposta $acao, Usuarios $autor): void
    {
        if ($acao === AcaoHistoricoAposta::CancelarPalpite) {
            if ($palpite->situacao !== SituacaoPalpite::Ativo) {
                throw new RegraApostaException('O palpite já está cancelado.');
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

        $palpite->fill(['situacao' => SituacaoPalpite::Ativo, 'restaurado_em' => now(), 'restaurado_por' => $autor->id])->save();
    }

    /**
     * Cotação total e prêmio só com os palpites ativos e os valores gravados na aposta; a
     * assinatura é refeita.
     */
    private function recalcular(Apostas $aposta): void
    {
        $cotacoes = $aposta->palpites_ativos()->orderBy('id')->pluck('cotacao_final')->map(fn ($cotacao) => (string) $cotacao)->all();

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
}
