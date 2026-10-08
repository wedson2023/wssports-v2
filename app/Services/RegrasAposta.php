<?php

namespace App\Services;

use App\Enums\SituacaoConfronto;
use App\Exceptions\RegraApostaException;
use App\Support\Apostador;
use App\Support\CodigosCotacao;
use Illuminate\Support\Carbon;

/**
 * Regras da aposta (FR-014 a FR-021), conferidas na ordem da spec com as configurações do público
 * do apostador. O que pode ser apostado é o mesmo que a listagem exibe (RegrasExibicao).
 */
class RegrasAposta
{
    public function __construct(private RegrasExibicao $exibicao) {}

    /**
     * Permissão do apostador e trava do sistema (FR-014, FR-019).
     *
     * @throws RegraApostaException
     */
    public function conferir_apostador(Apostador $apostador): void
    {
        if (! $apostador->ativo()) {
            throw new RegraApostaException('Seu login está inativo.');
        }

        if (! $apostador->realizar_aposta()) {
            throw new RegraApostaException('Você não tem permissão para realizar apostas.');
        }

        if ($this->exibicao->sistema_travado($apostador->publico)) {
            throw new RegraApostaException('Sistema travado, procure seu gerente.');
        }
    }

    /**
     * Palpites da aposta (FR-015 a FR-020); para no primeiro palpite que não pode ser apostado.
     *
     * @param  list<array<string, mixed>>  $palpites  palpites montados (CriacaoApostas::montar)
     *
     * @throws RegraApostaException
     */
    public function conferir_palpites(Apostador $apostador, array $palpites): void
    {
        if ($palpites === []) {
            throw new RegraApostaException('Escolha os jogos antes de concluir a aposta.');
        }

        $confrontos = array_column($palpites, 'confrontos_id');

        if (count($confrontos) !== count(array_unique($confrontos))) {
            throw new RegraApostaException('Não é possível cadastrar jogos repetidos na aposta.');
        }

        $this->conferir_ao_vivo($apostador, $palpites);

        foreach ($this->motivos($apostador, $palpites) as $motivo) {
            if ($motivo !== null) {
                throw new RegraApostaException($motivo);
            }
        }
    }

    /**
     * Motivo de cada palpite não poder ser apostado (null = pode), na mesma ordem dos palpites.
     *
     * @param  list<array<string, mixed>>  $palpites
     * @return list<string|null>
     */
    public function motivos(Apostador $apostador, array $palpites): array
    {
        [$visiveis_pre_jogo, $visiveis_ao_vivo] = $this->visiveis($apostador, $palpites);

        return array_map(
            fn (array $palpite) => $this->motivo($apostador, $palpite, $visiveis_pre_jogo, $visiveis_ao_vivo),
            $palpites,
        );
    }

    /**
     * Quantidade de palpites, valor e cotação total (FR-021).
     *
     * @throws RegraApostaException
     */
    public function conferir_limites(Apostador $apostador, string $valor, int $quantidade, string $cotacao_total): void
    {
        $minimo = $apostador->quantidade_minima_opcoes();
        $maximo = $apostador->quantidade_maxima_opcoes();

        if ($quantidade < $minimo || $quantidade > $maximo) {
            throw new RegraApostaException("Só é permitido apostar entre {$minimo} e {$maximo} jogos.");
        }

        if (bccomp($valor, $apostador->valor_minimo_aposta(), 2) < 0 || bccomp($valor, $apostador->valor_maximo_aposta(), 2) > 0) {
            throw new RegraApostaException('Só é permitido apostar entre '.self::reais($apostador->valor_minimo_aposta())
                .' e '.self::reais($apostador->valor_maximo_aposta()).'.');
        }

        if (bccomp($cotacao_total, $apostador->odd_minima(), 2) < 0) {
            throw new RegraApostaException('A cotação total deve ser maior ou igual a '.self::cotacao($apostador->odd_minima()).'.');
        }

        $odd_maxima = $apostador->odd_maxima();

        if ($odd_maxima !== null && bccomp($cotacao_total, $odd_maxima, 2) > 0) {
            throw new RegraApostaException('A cotação total deve ser menor ou igual a '.self::cotacao($odd_maxima).'.');
        }
    }

    public static function reais(string $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    public static function cotacao(string $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    /**
     * Nome do confronto como nas mensagens do sistema antigo: "CASA X FORA".
     *
     * @param  array<string, mixed>  $palpite
     */
    public static function nome_confronto(array $palpite): string
    {
        $jogo = $palpite['ao_vivo'] ?? $palpite['confronto'];

        return $jogo === null ? 'informado' : mb_strtoupper("{$jogo->time_casa} x {$jogo->time_fora}");
    }

    /**
     * Ao vivo na aposta: proibido ao visitante e exige a permissão do público (FR-020).
     *
     * @param  list<array<string, mixed>>  $palpites
     */
    private function conferir_ao_vivo(Apostador $apostador, array $palpites): void
    {
        if (! in_array(true, array_map(fn (array $palpite) => $palpite['confrontos_ao_vivo_id'] !== null, $palpites), true)) {
            return;
        }

        if ($apostador->e_visitante()) {
            throw new RegraApostaException('Para apostar no ao vivo é preciso fazer login.');
        }

        if (! $apostador->apostar_ao_vivo() || ! $this->exibicao->ao_vivo_habilitado($apostador->publico)) {
            throw new RegraApostaException('Você não tem permissão para apostar em jogos ao vivo.');
        }
    }

    /**
     * Ids dos jogos da aposta que a listagem mostra ao público: [pré-jogo, ao vivo].
     *
     * @param  list<array<string, mixed>>  $palpites
     * @return array{0: list<int>, 1: list<int>}
     */
    private function visiveis(Apostador $apostador, array $palpites): array
    {
        $ids_pre_jogo = array_values(array_filter(array_map(
            fn (array $palpite) => $palpite['confrontos_ao_vivo_id'] === null ? $palpite['confrontos_id'] : null,
            $palpites,
        )));
        $ids_ao_vivo = array_values(array_filter(array_column($palpites, 'confrontos_ao_vivo_id')));

        return [
            $ids_pre_jogo === [] ? [] : $this->exibicao->consulta_pre_jogo($apostador->publico)
                ->whereIn('co.id', $ids_pre_jogo)->pluck('co.id')->map(fn ($id) => (int) $id)->all(),
            $ids_ao_vivo === [] ? [] : $this->exibicao->consulta_ao_vivo($apostador->publico)
                ->whereIn('av.id', $ids_ao_vivo)->pluck('av.id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * Primeiro motivo pelo qual o palpite não pode ser apostado, na ordem da spec; null = pode.
     *
     * @param  array<string, mixed>  $palpite
     * @param  list<int>  $visiveis_pre_jogo
     * @param  list<int>  $visiveis_ao_vivo
     */
    private function motivo(Apostador $apostador, array $palpite, array $visiveis_pre_jogo, array $visiveis_ao_vivo): ?string
    {
        if ($palpite['motivo'] !== null) {
            return $palpite['motivo'];
        }

        $nome = self::nome_confronto($palpite);
        $ao_vivo = $palpite['confrontos_ao_vivo_id'] !== null;
        $confronto = $palpite['confronto'];

        if ($palpite['codigo_cotacao'] === CodigosCotacao::JOGADOR) {
            $motivo_jogador = $this->motivo_jogador($apostador, $palpite, $ao_vivo);

            if ($motivo_jogador !== null) {
                return $motivo_jogador;
            }
        }

        if (! $confronto->ativo || ! $confronto->campeonato_ativo) {
            return "O confronto {$nome} está inativo, retire-o para concluir.";
        }

        if ($ao_vivo) {
            return $this->motivo_ao_vivo($apostador, $palpite, $nome, $visiveis_ao_vivo);
        }

        $inicio = Carbon::parse($confronto->data_inicio, 'UTC');

        if ($confronto->situacao !== SituacaoConfronto::Aguardando->value || $inicio->lessThanOrEqualTo(now()) || $palpite['em_andamento']) {
            return "O confronto {$nome} já iniciou, retire-o para concluir.";
        }

        $travamento = $this->exibicao->data_travamento($apostador->publico);

        if ($travamento !== null && $inicio->greaterThan($travamento)) {
            return "O confronto {$nome} está fora do horário limite estabelecido pela banca, retire-o para concluir.";
        }

        if ($inicio->greaterThan($this->exibicao->limite_pre_jogo($apostador->publico))) {
            return "O confronto {$nome} está fora do período de jogos permitido, retire-o para concluir.";
        }

        if (! $this->exibicao->esporte_permitido($apostador->publico, $palpite['esporte'])
            || ! in_array($palpite['confrontos_id'], $visiveis_pre_jogo, true)) {
            return "O confronto {$nome} não está disponível para apostas, retire-o para concluir.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $palpite
     * @param  list<int>  $visiveis_ao_vivo
     */
    private function motivo_ao_vivo(Apostador $apostador, array $palpite, string $nome, array $visiveis_ao_vivo): ?string
    {
        $jogo = $palpite['ao_vivo'];

        if (! in_array($jogo->situacao, RegrasExibicao::situacoes_ao_vivo(), true)
            || (int) $jogo->minuto > $this->exibicao->minuto_limite_ao_vivo($apostador->publico)) {
            return "O confronto {$nome} já foi encerrado ou passou do tempo para aposta, retire-o para concluir.";
        }

        if (! $this->exibicao->esporte_permitido($apostador->publico, $palpite['esporte'])
            || ! in_array($palpite['confrontos_ao_vivo_id'], $visiveis_ao_vivo, true)) {
            return "O confronto {$nome} não está disponível para apostas, retire-o para concluir.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $palpite
     */
    private function motivo_jogador(Apostador $apostador, array $palpite, bool $ao_vivo): ?string
    {
        if ($ao_vivo) {
            return 'Não é possível apostar em jogador no ao vivo.';
        }

        if (! $this->exibicao->pode_apostar_jogadores($apostador->publico)) {
            return 'Apostas em jogadores não estão disponíveis.';
        }

        if ($palpite['jogador'] === null || (int) $palpite['jogador']->confrontos_id !== $palpite['confrontos_id']) {
            return 'Jogador não pertence ao confronto.';
        }

        return null;
    }
}
