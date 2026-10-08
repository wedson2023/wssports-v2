<?php

namespace App\Services;

use App\Enums\SituacaoAposta;
use App\Enums\SituacaoPalpite;
use App\Exceptions\CotacoesAlteradasException;
use App\Exceptions\RegraApostaException;
use App\Models\Apostas;
use App\Models\ApostasPalpites;
use App\Models\Usuarios;
use App\Support\Apostador;
use Illuminate\Support\Facades\DB;

/**
 * Código do visitante: a simulação mostra a aposta com as cotações e as regras do vendedor, sem
 * gravar nada; a validação a transforma em aposta Ativa do vendedor, uma única vez (FR-038 a FR-041).
 */
class ValidacaoCodigos
{
    private const NAO_ENCONTRADA = 'Aposta não encontrada ou já validada.';

    private const TAMANHO_USER_AGENT = 255;

    public function __construct(private CriacaoApostas $criacao, private RegrasAposta $regras) {}

    /**
     * @return array{aposta: Apostas, palpites: list<array<string, mixed>>, calculo: array<string, string>}
     *
     * @throws RegraApostaException quando o código expirou
     */
    public function simular(Usuarios $vendedor, string $codigo): array
    {
        $aposta = $this->pendente($codigo);
        $apostador = Apostador::vendedor($vendedor);

        $palpites = $this->criacao->montar($apostador, $this->pedidos($aposta));
        $motivos = $this->regras->motivos($apostador, $palpites);

        foreach ($palpites as $indice => $palpite) {
            $palpites[$indice]['motivo'] = $motivos[$indice]
                ?? (bccomp($palpite['cotacao_atual'], '0', 2) <= 0 ? 'Cotação indisponível.' : null);
        }

        $disponiveis = array_filter($palpites, fn (array $palpite) => $palpite['motivo'] === null);

        return [
            'aposta' => $aposta,
            'palpites' => $palpites,
            'calculo' => $this->criacao->calcular($apostador, (string) $aposta->valor, array_values(array_column($disponiveis, 'cotacao_atual'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados  pedido validado, com os palpites ajustados pelo vendedor
     * @return array{0: Apostas, 1: bool} a aposta validada e se é uma validação repetida
     *
     * @throws RegraApostaException|CotacoesAlteradasException
     */
    public function validar(Usuarios $vendedor, string $codigo, array $dados, ?string $ip, ?string $user_agent): array
    {
        $repetida = Apostas::where('chave_validacao', $dados['chave_idempotencia'])->first();

        if ($repetida !== null) {
            if ($repetida->usuarios_id !== $vendedor->id) {
                throw new RegraApostaException('Chave de idempotência já usada.');
            }

            return [$repetida, true];
        }

        $this->pendente($codigo);

        if (array_filter(array_column($dados['palpites'], 'confrontos_ao_vivo_id')) !== []) {
            throw new RegraApostaException('Não é possível adicionar jogos ao vivo na validação do código.');
        }

        $apostador = Apostador::vendedor($vendedor);

        $aposta = DB::transaction(function () use ($apostador, $codigo, $dados, $ip, $user_agent) {
            // ordem de bloqueio (R-07): aposta → confrontos → vendedor
            $aposta = Apostas::buscar_pelo_codigo($codigo)->lockForUpdate()->first();

            abort_unless($aposta !== null && $aposta->situacao === SituacaoAposta::Pendente, 404, self::NAO_ENCONTRADA);

            if ($this->expirada($aposta)) {
                throw new RegraApostaException('Aposta expirada.');
            }

            $preparo = $this->criacao->preparar($apostador, $dados);
            $this->criacao->garantir_limite_por_confronto($preparo['palpites'], $preparo['valor']);

            $aposta->fill([
                'nome' => $dados['nome'],
                'aceitar_alteracoes' => $preparo['aceitar'],
                'valor' => $preparo['valor'],
                'cotacao_total' => $preparo['calculo']['cotacao_total'],
                'premio' => $preparo['calculo']['premio'],
                'valor_acrescido' => $preparo['calculo']['valor_acrescido'],
                'multiplicador' => $apostador->multiplicador(),
                'premio_maximo' => $apostador->premio_maximo(),
                'ganho_multiplo_palpites' => $apostador->ganho_multiplo_palpites(),
                'usuarios_id' => $apostador->usuario()->id,
                'validada_em' => now(),
                'ip_validacao' => $ip,
                'user_agent_validacao' => $user_agent !== null ? mb_substr($user_agent, 0, self::TAMANHO_USER_AGENT) : null,
                'chave_validacao' => $dados['chave_idempotencia'],
            ]);
            $aposta->save();

            $this->substituir_palpites($aposta, $preparo['palpites']);
            $this->criacao->confirmar($apostador, $aposta, count($preparo['palpites']), false);

            return $aposta;
        });

        return [$aposta, false];
    }

    /**
     * Aposta Pendente pelo código; expirada é marcada e recusada (FR-040, FR-041).
     *
     * @throws RegraApostaException
     */
    private function pendente(string $codigo): Apostas
    {
        $aposta = Apostas::buscar_pelo_codigo($codigo)->first();

        abort_unless($aposta !== null && $aposta->situacao === SituacaoAposta::Pendente, 404, self::NAO_ENCONTRADA);

        if ($this->expirada($aposta)) {
            // atualização condicional: não disputa com uma validação simultânea
            Apostas::whereKey($aposta->id)->where('situacao', SituacaoAposta::Pendente)->update([
                'situacao' => SituacaoAposta::Expirada,
                'motivo_recusa' => 'Código expirado.',
            ]);

            throw new RegraApostaException('Aposta expirada.');
        }

        return $aposta;
    }

    /**
     * Passou a validade do código ou algum jogo já começou.
     */
    public function expirada(Apostas $aposta): bool
    {
        if ($aposta->expira_em !== null && $aposta->expira_em->lessThanOrEqualTo(now())) {
            return true;
        }

        return DB::table('apostas_palpites as ap')
            ->join('confrontos as co', 'co.id', '=', 'ap.confrontos_id')
            ->where('ap.apostas_id', $aposta->id)
            ->whereNull('ap.deleted_at')
            ->where('co.data_inicio', '<=', now())
            ->exists();
    }

    /**
     * Palpites gravados no formato do pedido, com a cotação gravada como "vista".
     *
     * @return list<array<string, mixed>>
     */
    private function pedidos(Apostas $aposta): array
    {
        return $aposta->palpites()->orderBy('id')->get()->map(fn (ApostasPalpites $palpite) => [
            'confrontos_id' => $palpite->confrontos_id,
            'codigo_cotacao' => $palpite->codigo_cotacao,
            'confrontos_jogadores_id' => $palpite->confrontos_jogadores_id,
            'cotacao_vista' => (string) $palpite->cotacao_final,
        ])->all();
    }

    /**
     * Troca os palpites do visitante pelos validados. O palpite de um confronto que continua na
     * aposta é atualizado (o índice único por aposta e confronto vale também para os excluídos);
     * os demais são excluídos logicamente e os novos, criados.
     *
     * @param  list<array<string, mixed>>  $palpites
     */
    private function substituir_palpites(Apostas $aposta, array $palpites): void
    {
        $existentes = ApostasPalpites::withTrashed()->where('apostas_id', $aposta->id)->get()->keyBy('confrontos_id');
        $novos = [];

        foreach ($palpites as $palpite) {
            $existente = $existentes[$palpite['confrontos_id']] ?? null;

            if ($existente === null) {
                $novos[] = $palpite;

                continue;
            }

            $existente->restore();
            $existente->fill([
                'campeonatos_id' => $palpite['campeonatos_id'],
                'esporte' => $palpite['esporte'],
                'codigo_cotacao' => $palpite['codigo_cotacao'],
                'confrontos_jogadores_id' => $palpite['confrontos_jogadores_id'],
                'jogador_tipo' => $palpite['jogador_tipo'],
                'cotacao_vista' => $palpite['cotacao_vista'],
                'cotacao_original' => $palpite['cotacao_original'],
                'cotacao_final' => $palpite['cotacao_atual'],
                'situacao' => SituacaoPalpite::Ativo,
            ])->save();
        }

        $mantidos = array_column($palpites, 'confrontos_id');

        ApostasPalpites::where('apostas_id', $aposta->id)->whereNotIn('confrontos_id', $mantidos)->delete();

        $this->criacao->gravar_palpites($aposta, $novos);
    }
}
