<?php

namespace App\Services;

use App\Enums\AcaoHistoricoAposta;
use App\Enums\MotivoCancelamentoPalpite;
use App\Enums\OrigemApuracao;
use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEspecial;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Especiais;
use App\Models\EspeciaisCorrecoes;
use App\Models\EspeciaisOpcoes;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encerramento, cancelamento, anulação de opção e correção de resultado das categorias especiais
 * (spec 006, FR-018 e FR-018a; spec 024). O palpite especial ganha o resultado pelas opções
 * vencedoras (uma ou mais; com empate na última vaga, a cotação das empatadas é dividida) e a aposta
 * é decidida e paga pela apuração da spec 009 (ApuracaoApostas e LiquidacaoApostas), inclusive na
 * correção, que estorna o pago a mais e credita a diferença.
 */
class EncerramentoEspeciais
{
    public function __construct(
        private EdicaoApostas $edicao,
        private ApuracaoApostas $apuracao,
        private LiquidacaoApostas $liquidacao,
    ) {}

    /**
     * @param  array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}  $resultado
     * @return int quantidade de apostas afetadas
     */
    public function encerrar(Especiais $especial, array $resultado, Usuarios $autor, string $ip, ?string $user_agent): int
    {
        return DB::transaction(function () use ($especial, $resultado, $autor, $ip, $user_agent) {
            $especial = $this->bloquear_aguardando($especial);

            $especial->forceFill([
                'situacao' => SituacaoEspecial::Encerrado,
                'dividir_empate' => $resultado['dividir_empate'],
                'encerrado_em' => now(),
                'encerrado_por' => $autor->id,
            ])->save();

            $this->marcar_opcoes($especial, $resultado);

            $palpites = $this->palpites_ativos($especial);
            $apostas_ids = $palpites->pluck('apostas_id')->unique()->all();
            $fotografias = $this->fotografias($apostas_ids);

            $this->aplicar_resultado($especial, $palpites, $resultado, $autor, $ip, $user_agent);

            return $this->decidir_apostas($apostas_ids, OrigemApuracao::EncerramentoEspecial, $fotografias);
        });
    }

    /**
     * Os palpites da categoria são cancelados (fora da cotação total, como cotação 1,00) e o prêmio
     * das apostas é recalculado, com o registro no histórico.
     *
     * @return int quantidade de apostas afetadas
     */
    public function cancelar(Especiais $especial, Usuarios $autor, string $ip, ?string $user_agent): int
    {
        return DB::transaction(function () use ($especial, $autor, $ip, $user_agent) {
            $especial = $this->bloquear_aguardando($especial);

            $especial->forceFill([
                'situacao' => SituacaoEspecial::Cancelado,
                'encerrado_em' => now(),
                'encerrado_por' => $autor->id,
            ])->save();

            $palpites = $this->palpites_ativos($especial);

            foreach ($palpites as $palpite) {
                $aposta = Apostas::lockForUpdate()->find($palpite->apostas_id);
                $this->edicao->cancelar_palpite_pelo_sistema($aposta, $palpite, $autor, $ip, $user_agent);
            }

            return $this->decidir_apostas($palpites->pluck('apostas_id')->unique()->all(), OrigemApuracao::EncerramentoEspecial);
        });
    }

    /**
     * Anula uma opção sem encerrar a categoria (spec 024, R-08): a opção sai do site e os palpites
     * nela são cancelados com o motivo "Opção anulada" (como cotação 1,00), com o prêmio recalculado;
     * a aposta que fica sem palpite pendente é decidida e paga.
     *
     * @return int quantidade de apostas afetadas
     */
    public function anular_opcao(Especiais $especial, EspeciaisOpcoes $opcao, Usuarios $autor, string $ip, ?string $user_agent): int
    {
        return DB::transaction(function () use ($especial, $opcao, $autor, $ip, $user_agent) {
            $especial = $this->bloquear_aguardando($especial);
            $opcao = EspeciaisOpcoes::lockForUpdate()->findOrFail($opcao->id);

            if ($opcao->anulada()) {
                throw ValidationException::withMessages(['opcao' => 'A opção já foi anulada.']);
            }

            $opcao->forceFill(['anulada_em' => now(), 'anulada_por' => $autor->id, 'ativo' => false])->save();

            $palpites = $this->palpites_ativos($especial, $opcao->id);

            foreach ($palpites as $palpite) {
                $aposta = Apostas::lockForUpdate()->find($palpite->apostas_id);
                $this->edicao->cancelar_palpite_pelo_sistema($aposta, $palpite, $autor, $ip, $user_agent, MotivoCancelamentoPalpite::OpçãoAnulada);
            }

            return $this->decidir_apostas($palpites->pluck('apostas_id')->unique()->all(), OrigemApuracao::EncerramentoEspecial);
        });
    }

    /**
     * Troca as vencedoras de uma categoria encerrada (spec 024, R-09 e R-10): todos os palpites
     * ativos dela em apostas ativas, inclusive as já decididas e pagas, recebem o resultado e a
     * cotação aplicada novos, e cada aposta é reapurada com a origem "Correção de especial".
     *
     * @param  array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}  $resultado
     * @return int quantidade de apostas afetadas
     */
    public function corrigir(Especiais $especial, array $resultado, Usuarios $autor, string $ip, ?string $user_agent): int
    {
        return DB::transaction(function () use ($especial, $resultado, $autor, $ip, $user_agent) {
            $especial = Especiais::lockForUpdate()->findOrFail($especial->id);

            if ($especial->situacao !== SituacaoEspecial::Encerrado) {
                throw ValidationException::withMessages(['especial' => 'Só uma categoria encerrada pode ter o resultado corrigido.']);
            }

            $anterior = $this->resultado_atual($especial);

            if ($anterior == $this->ordenado($resultado)) {
                throw ValidationException::withMessages(['vencedoras' => 'O resultado é o mesmo.']);
            }

            $especial->forceFill(['dividir_empate' => $resultado['dividir_empate']])->save();
            $this->marcar_opcoes($especial, $resultado);

            $palpites = ApostasPalpites::where('especiais_id', $especial->id)
                ->where('situacao', SituacaoPalpite::Ativo->value)
                ->whereHas('aposta', fn ($aposta) => $aposta->where('situacao', SituacaoAposta::Ativa->value))
                ->lockForUpdate()
                ->get();
            $apostas_ids = $palpites->pluck('apostas_id')->unique()->all();
            $fotografias = $this->fotografias($apostas_ids);

            $this->aplicar_resultado($especial, $palpites, $resultado, $autor, $ip, $user_agent);
            $afetadas = $this->decidir_apostas($apostas_ids, OrigemApuracao::CorreçãoEspecial, $fotografias);

            EspeciaisCorrecoes::create([
                'especiais_id' => $especial->id,
                'vencedoras_anteriores' => $anterior['vencedoras'],
                'empatadas_anteriores' => $anterior['empatadas'],
                'dividir_empate_anterior' => $anterior['dividir_empate'],
                'vencedoras_novas' => $resultado['vencedoras'],
                'empatadas_novas' => $resultado['empatadas'],
                'dividir_empate_novo' => $resultado['dividir_empate'],
                'apostas_afetadas' => $afetadas,
                'usuarios_id' => $autor->id,
            ]);

            return $afetadas;
        });
    }

    /**
     * Bloqueia a categoria e recusa a que já foi encerrada ou cancelada.
     */
    private function bloquear_aguardando(Especiais $especial): Especiais
    {
        $bloqueada = Especiais::lockForUpdate()->findOrFail($especial->id);

        if ($bloqueada->situacao !== SituacaoEspecial::Aguardando) {
            throw ValidationException::withMessages(['especial' => 'A categoria já foi encerrada ou cancelada.']);
        }

        return $bloqueada;
    }

    /**
     * Grava nas opções não anuladas quais venceram e quais empataram na última vaga.
     *
     * @param  array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}  $resultado
     */
    private function marcar_opcoes(Especiais $especial, array $resultado): void
    {
        $especial->opcoes()->whereNull('anulada_em')->update(['vencedora' => false, 'empatada' => false]);
        $especial->opcoes()->whereIn('id', $resultado['vencedoras'])->update(['vencedora' => true]);
        $especial->opcoes()->whereIn('id', $resultado['empatadas'])->update(['empatada' => true]);
    }

    /**
     * Resultado de cada palpite (Vencedor nas vencedoras, Perdedor nas demais) e a cotação aplicada
     * nas empatadas; quando a cotação aplicada muda (inclusive ao desfazer a divisão), o prêmio da
     * aposta é recalculado e registrado no histórico.
     *
     * @param  Collection<int, ApostasPalpites>  $palpites
     * @param  array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}  $resultado
     */
    private function aplicar_resultado(Especiais $especial, Collection $palpites, array $resultado, Usuarios $autor, string $ip, ?string $user_agent): void
    {
        $empatadas = count($resultado['empatadas']);
        $vagas = $especial->quantidade_vencedoras - (count($resultado['vencedoras']) - $empatadas);

        foreach ($palpites as $palpite) {
            $opcao = (int) $palpite->especiais_opcoes_id;
            $cotacao_empate = in_array($opcao, $resultado['empatadas'], true)
                ? $this->cotacao_empate((string) $palpite->cotacao_final, $vagas, $empatadas)
                : null;
            $mudou_cotacao = $cotacao_empate !== ($palpite->cotacao_empate === null ? null : (string) $palpite->cotacao_empate);

            $palpite->forceFill([
                'resultado' => in_array($opcao, $resultado['vencedoras'], true) ? ResultadoAposta::Vencedor : ResultadoAposta::Perdedor,
                'cotacao_empate' => $cotacao_empate,
            ])->save();

            if ($mudou_cotacao) {
                $aposta = Apostas::lockForUpdate()->find($palpite->apostas_id);
                $this->edicao->recalcular_pelo_sistema($aposta, $palpite, AcaoHistoricoAposta::DivisãoEmpate, $autor, $ip, $user_agent);
            }
        }
    }

    /**
     * Cotação × vagas ÷ empatadas, arredondada para baixo em duas casas (o bcdiv trunca); pode
     * ficar abaixo de 1,00, como no "dead heat" (FR-015).
     */
    private function cotacao_empate(string $cotacao, int $vagas, int $empatadas): string
    {
        return bcdiv(bcmul($cotacao, (string) $vagas, 4), (string) $empatadas, 2);
    }

    /**
     * Vencedoras, empatadas e divisão gravadas na categoria, com os ids em ordem.
     *
     * @return array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}
     */
    private function resultado_atual(Especiais $especial): array
    {
        $vencedoras = $especial->opcoes()->where('vencedora', true)->get(['id', 'empatada']);

        return $this->ordenado([
            'vencedoras' => $vencedoras->pluck('id')->all(),
            'empatadas' => $vencedoras->where('empatada', true)->pluck('id')->all(),
            'dividir_empate' => $especial->dividir_empate,
        ]);
    }

    /**
     * @param  array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}  $resultado
     * @return array{vencedoras: list<int>, empatadas: list<int>, dividir_empate: bool}
     */
    private function ordenado(array $resultado): array
    {
        $ids = fn (array $lista) => collect($lista)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return [
            'vencedoras' => $ids($resultado['vencedoras']),
            'empatadas' => $ids($resultado['empatadas']),
            'dividir_empate' => (bool) $resultado['dividir_empate'],
        ];
    }

    /**
     * Palpites ativos da categoria (ou só de uma opção dela) em apostas ativas e ainda sem
     * resultado. Apostas pendentes (código não validado) e em análise não são tocadas: o especial
     * fica indisponível nelas.
     *
     * @return Collection<int, ApostasPalpites>
     */
    private function palpites_ativos(Especiais $especial, ?int $opcoes_id = null): Collection
    {
        return ApostasPalpites::where('especiais_id', $especial->id)
            ->when($opcoes_id !== null, fn ($palpites) => $palpites->where('especiais_opcoes_id', $opcoes_id))
            ->where('situacao', SituacaoPalpite::Ativo->value)
            ->whereHas('aposta', fn ($aposta) => $aposta->where('situacao', SituacaoAposta::Ativa->value)
                ->where('resultado', ResultadoAposta::Aguardando->value))
            ->lockForUpdate()
            ->get();
    }

    /**
     * Resultado e total a pagar de cada aposta antes de o resultado mudar a cotação aplicada, para
     * a apuração saber o que mudou.
     *
     * @param  list<int>  $apostas_ids
     * @return array<int, array{resultado: string, total_a_pagar: string}>
     */
    private function fotografias(array $apostas_ids): array
    {
        return collect($apostas_ids)
            ->mapWithKeys(fn (int $apostas_id) => [$apostas_id => $this->apuracao->fotografia(Apostas::lockForUpdate()->find($apostas_id))])
            ->all();
    }

    /**
     * Decide e liquida cada aposta pela mesma regra da apuração dos jogos (spec 009, R-09), para
     * que apostas só de especiais e mistas (especial + jogos) sejam decididas e pagas igual. Sem
     * fotografia de antes, ela é tirada aqui.
     *
     * @param  list<int>  $apostas_ids
     * @param  array<int, array{resultado: string, total_a_pagar: string}>  $fotografias
     */
    private function decidir_apostas(array $apostas_ids, OrigemApuracao $origem, array $fotografias = []): int
    {
        foreach ($apostas_ids as $apostas_id) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);

            $this->apuracao->decidir($aposta, $origem, $fotografias[$apostas_id] ?? $this->apuracao->fotografia($aposta));
            $this->liquidacao->liquidar($aposta, $origem);
        }

        return count($apostas_ids);
    }
}
