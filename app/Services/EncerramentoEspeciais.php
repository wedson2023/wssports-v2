<?php

namespace App\Services;

use App\Enums\ResultadoAposta;
use App\Enums\SituacaoAposta;
use App\Enums\SituacaoEspecial;
use App\Enums\SituacaoPalpite;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Especiais;
use App\Models\EspeciaisOpcoes;
use App\Models\Usuarios;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encerramento e cancelamento de categorias especiais (spec 006, FR-018 e FR-018a), como no
 * sistema antigo no que não depende dos jogos: o palpite especial ganha o resultado comparando com
 * a opção vencedora, e a aposta recebe o resultado quando ele já pode ser decidido. Nenhum saldo é
 * movimentado (o pagamento é da spec de apuração).
 */
class EncerramentoEspeciais
{
    public function __construct(private EdicaoApostas $edicao) {}

    /**
     * @return int quantidade de apostas afetadas
     */
    public function encerrar(Especiais $especial, EspeciaisOpcoes $vencedora, Usuarios $autor): int
    {
        return DB::transaction(function () use ($especial, $vencedora, $autor) {
            $especial = $this->bloquear_aguardando($especial);

            $especial->forceFill([
                'situacao' => SituacaoEspecial::Encerrado,
                'especiais_opcoes_id_vencedora' => $vencedora->id,
                'encerrado_em' => now(),
                'encerrado_por' => $autor->id,
            ])->save();

            $palpites = $this->palpites_ativos($especial);

            foreach ($palpites as $palpite) {
                $palpite->resultado = $palpite->especiais_opcoes_id === $vencedora->id ? ResultadoAposta::Vencedor : ResultadoAposta::Perdedor;
                $palpite->save();
            }

            return $this->decidir_apostas($palpites->pluck('apostas_id')->unique()->all());
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

            return $this->decidir_apostas($palpites->pluck('apostas_id')->unique()->all());
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
     * Palpites ativos da categoria em apostas ativas e ainda sem resultado. Apostas pendentes
     * (código não validado) e em análise não são tocadas: o especial fica indisponível nelas.
     *
     * @return Collection<int, ApostasPalpites>
     */
    private function palpites_ativos(Especiais $especial)
    {
        return ApostasPalpites::where('especiais_id', $especial->id)
            ->where('situacao', SituacaoPalpite::Ativo->value)
            ->whereHas('aposta', fn ($aposta) => $aposta->where('situacao', SituacaoAposta::Ativa->value)
                ->where('resultado', ResultadoAposta::Aguardando->value))
            ->lockForUpdate()
            ->get();
    }

    /**
     * Resultado de cada aposta, olhando só os palpites ativos: Perdedor se algum perdeu; Vencedor
     * se não restou palpite ativo ou se todos são especiais vencedores; senão segue Aguardando.
     *
     * @param  list<int>  $apostas_ids
     */
    private function decidir_apostas(array $apostas_ids): int
    {
        foreach ($apostas_ids as $apostas_id) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);
            $ativos = $aposta->palpites_ativos()->get();

            $resultado = match (true) {
                $ativos->contains(fn (ApostasPalpites $palpite) => $palpite->resultado === ResultadoAposta::Perdedor) => ResultadoAposta::Perdedor,
                $ativos->every(fn (ApostasPalpites $palpite) => $palpite->e_especial() && $palpite->resultado === ResultadoAposta::Vencedor) => ResultadoAposta::Vencedor,
                default => ResultadoAposta::Aguardando,
            };

            if ($resultado !== ResultadoAposta::Aguardando) {
                $aposta->resultado = $resultado;
                $aposta->save();
            }
        }

        return count($apostas_ids);
    }
}
