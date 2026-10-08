<?php

namespace App\Services;

use App\Enums\AceitarAlteracoes;
use App\Enums\SituacaoAposta;
use App\Exceptions\RegraApostaException;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Clientes;
use App\Models\Usuarios;
use App\Support\Apostador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decisão da aposta do ao vivo no fim do delay (R-02, FR-034): relê os jogos e decide sozinha,
 * uma única vez, sem consultar o apostador de novo.
 */
class DecisaoAoVivo
{
    /**
     * Campos da fotografia do jogo que indicam um lance: se algum mudou, a aposta é recusada.
     */
    private const CAMPOS_LANCE = [
        'placar_casa',
        'placar_fora',
        'gols_primeiro_tempo_casa',
        'gols_primeiro_tempo_fora',
        'gols_segundo_tempo_casa',
        'gols_segundo_tempo_fora',
        'escanteios_casa',
        'escanteios_fora',
        'situacao',
    ];

    private const TAMANHO_MOTIVO = 255;

    public function __construct(
        private CriacaoApostas $criacao,
        private RegrasAposta $regras,
        private ConferenciaCotacoes $conferencia,
    ) {}

    public function decidir(int $apostas_id): void
    {
        DB::transaction(function () use ($apostas_id) {
            $aposta = Apostas::lockForUpdate()->find($apostas_id);

            // já decidida (job repetido ou recusada pelo comando de análises presas)
            if ($aposta === null || $aposta->situacao !== SituacaoAposta::EmAnálise) {
                return;
            }

            $gravados = $aposta->palpites()->orderBy('id')->get();

            try {
                // ponto de salvamento: se a aceitação falhar no meio, nada dela fica gravado
                DB::transaction(fn () => $this->aceitar($aposta, $gravados));
            } catch (RegraApostaException|SaldoInsuficienteException $excecao) {
                $this->recusar($aposta, $gravados, $excecao instanceof SaldoInsuficienteException
                    ? 'Você não tem saldo suficiente para realizar esta aposta.'
                    : $excecao->getMessage());
            }
        });
    }

    /**
     * Recusa uma aposta Em análise sem mover dinheiro nem limites.
     *
     * @param  Collection<int, ApostasPalpites>  $gravados
     */
    public function recusar(Apostas $aposta, Collection $gravados, string $motivo): void
    {
        foreach ($gravados as $palpite) {
            if ($palpite->confrontos_ao_vivo_id !== null) {
                $jogo = DB::table('confrontos_ao_vivo')->find($palpite->confrontos_ao_vivo_id);
                $palpite->dados_ao_vivo_decisao = $jogo !== null ? CriacaoApostas::fotografia($jogo) : null;
                $palpite->save();
            }
        }

        $aposta->situacao = SituacaoAposta::Recusada;
        $aposta->motivo_recusa = mb_substr($motivo, 0, self::TAMANHO_MOTIVO);
        $aposta->decidida_em = now();
        $aposta->save();
    }

    /**
     * @param  Collection<int, ApostasPalpites>  $gravados
     *
     * @throws RegraApostaException|SaldoInsuficienteException
     */
    private function aceitar(Apostas $aposta, Collection $gravados): void
    {
        $apostador = $this->apostador($aposta);

        $this->regras->conferir_apostador($apostador);

        // as cotações aceitas no envio ficam como "vistas" para a comparação
        $palpites = $this->criacao->montar($apostador, $gravados->map(fn (ApostasPalpites $palpite) => [
            'confrontos_id' => $palpite->confrontos_id,
            'confrontos_ao_vivo_id' => $palpite->confrontos_ao_vivo_id,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'confrontos_jogadores_id' => $palpite->confrontos_jogadores_id,
            'cotacao_vista' => (string) $palpite->cotacao_final,
        ])->all());

        $this->regras->conferir_palpites($apostador, $palpites);
        $this->conferir_lances($aposta, $gravados, $palpites);
        $this->conferencia->garantir_disponiveis($palpites);

        $finais = $this->cotacoes_finais($palpites, $aposta->aceitar_alteracoes);
        $valor = (string) $aposta->valor;
        $calculo = $this->criacao->calcular($apostador, $valor, $finais);

        $this->regras->conferir_limites($apostador, $valor, count($palpites), $calculo['cotacao_total']);

        if ($apostador->e_cliente()) {
            $this->criacao->garantir_valor_diario($apostador, $valor, $aposta->id);
        }

        foreach ($gravados->values() as $indice => $palpite) {
            $palpite->cotacao_final = $finais[$indice];
            $palpite->dados_ao_vivo_decisao = $palpites[$indice]['ao_vivo'] !== null ? CriacaoApostas::fotografia($palpites[$indice]['ao_vivo']) : null;
            $palpite->save();
        }

        $aposta->fill([
            'cotacao_total' => $calculo['cotacao_total'],
            'premio' => $calculo['premio'],
            'valor_acrescido' => $calculo['valor_acrescido'],
            'decidida_em' => now(),
        ]);

        $this->criacao->confirmar($apostador, $aposta, count($palpites), true);
    }

    /**
     * Recusa se houve lance (placar, gols, escanteios ou situação mudaram) ou se o jogo não recebeu
     * nenhuma atualização depois do envio (sem dado novo não dá para garantir que nada aconteceu).
     *
     * @param  Collection<int, ApostasPalpites>  $gravados
     * @param  list<array<string, mixed>>  $palpites
     *
     * @throws RegraApostaException
     */
    private function conferir_lances(Apostas $aposta, Collection $gravados, array $palpites): void
    {
        foreach ($gravados->values() as $indice => $gravado) {
            $jogo = $palpites[$indice]['ao_vivo'];

            if ($jogo === null) {
                continue;
            }

            $antes = $gravado->dados_ao_vivo_envio ?? [];
            $agora = CriacaoApostas::fotografia($jogo);
            $nome = RegrasAposta::nome_confronto($palpites[$indice]);

            foreach (self::CAMPOS_LANCE as $campo) {
                if (($antes[$campo] ?? null) !== $agora[$campo]) {
                    throw new RegraApostaException("Houve lance no confronto {$nome} durante a análise.");
                }
            }

            if (! Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->greaterThan($aposta->recebida_em)) {
                throw new RegraApostaException("O confronto {$nome} não recebeu atualização durante a análise.");
            }
        }
    }

    /**
     * Cotação final de cada palpite pela preferência: caiu → recusa (só Qualquer aceita a nova);
     * subiu → a nova com Somente para maior ou Qualquer, a congelada com Nenhuma.
     *
     * @param  list<array<string, mixed>>  $palpites
     * @return list<string>
     *
     * @throws RegraApostaException
     */
    private function cotacoes_finais(array $palpites, AceitarAlteracoes $preferencia): array
    {
        return array_map(function (array $palpite) use ($preferencia) {
            $congelada = $palpite['cotacao_vista'];
            $atual = $palpite['cotacao_atual'];
            $comparacao = bccomp($atual, $congelada, 2);

            if ($comparacao < 0 && $preferencia !== AceitarAlteracoes::Qualquer) {
                throw new RegraApostaException('A cotação do confronto '.RegrasAposta::nome_confronto($palpite).' caiu durante a análise.');
            }

            return $comparacao !== 0 && $preferencia !== AceitarAlteracoes::Nenhuma ? $atual : $congelada;
        }, $palpites);
    }

    /**
     * Apostador da aposta, relido do banco (pode ter sido desativado durante o delay).
     */
    private function apostador(Apostas $aposta): Apostador
    {
        return $aposta->clientes_id !== null
            ? Apostador::cliente(Clientes::withTrashed()->findOrFail($aposta->clientes_id))
            : Apostador::vendedor(Usuarios::withTrashed()->findOrFail($aposta->usuarios_id));
    }
}
