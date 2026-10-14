<?php

namespace App\Services;

use App\Enums\SituacaoConfronto;
use App\Exceptions\RegraApostaException;
use App\Support\Apostador;
use App\Support\CodigosCotacao;
use App\Support\MercadosCotacao;
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

        $this->conferir_repetidos($palpites);

        // um palpite por categoria especial (spec 006, FR-015)
        $especiais = array_values(array_filter(array_column($palpites, 'especiais_id'), fn (?int $id) => $id !== null));

        if (count($especiais) !== count(array_unique($especiais))) {
            throw new RegraApostaException('Escolha só uma opção por categoria especial.');
        }

        $this->conferir_ao_vivo($apostador, $palpites);

        foreach ($this->motivos($apostador, $palpites) as $motivo) {
            if ($motivo !== null) {
                throw new RegraApostaException($motivo);
            }
        }
    }

    /**
     * Blocos Criar Aposta (spec 008, FR-001 a FR-005a, FR-013): palpites repetidos de um jogo do
     * pré-jogo formam um bloco, que precisa estar liberado, ter a cotação vista informada, ficar entre
     * o mínimo e o máximo do público e não combinar palpites do mesmo mercado ou redundantes.
     *
     * @param  list<array<string, mixed>>  $palpites  palpites montados (CriacaoApostas::montar)
     * @param  list<array{confrontos_id: int, cotacao_vista: string}>  $blocos  blocos do pedido
     *
     * @throws RegraApostaException
     */
    public function conferir_blocos(Apostador $apostador, array $palpites, array $blocos): void
    {
        $jogos = self::palpites_por_jogo_pre_jogo($palpites);
        $repetidos = array_filter($jogos, fn (array $do_jogo) => count($do_jogo) > 1);
        $informados = array_column($blocos, 'confrontos_id');

        foreach ($informados as $confrontos_id) {
            if (! isset($repetidos[$confrontos_id])) {
                throw new RegraApostaException('O Criar Aposta precisa de palpites do mesmo jogo.');
            }
        }

        if ($repetidos === []) {
            return;
        }

        if (! $apostador->criar_aposta_habilitado()) {
            throw new RegraApostaException('O Criar Aposta não está habilitado.');
        }

        $minimo = $apostador->quantidade_minima_criar_aposta();
        $maximo = $apostador->quantidade_maxima_criar_aposta();

        foreach ($repetidos as $confrontos_id => $do_jogo) {
            $nome = self::nome_confronto($do_jogo[0]);

            if (! in_array($confrontos_id, $informados, true)) {
                throw new RegraApostaException("Informe a cotação do Criar Aposta do jogo {$nome}.");
            }

            if (count($do_jogo) < $minimo) {
                throw new RegraApostaException("O Criar Aposta do jogo {$nome} precisa de pelo menos {$minimo} palpites.");
            }

            if (count($do_jogo) > $maximo) {
                throw new RegraApostaException("O Criar Aposta aceita no máximo {$maximo} palpites.");
            }

            $this->conferir_combinacoes($do_jogo);
        }
    }

    /**
     * Palpites do pré-jogo agrupados pelo confronto (ao vivo e especial ficam de fora).
     *
     * @param  list<array<string, mixed>>  $palpites
     * @return array<int, list<array<string, mixed>>>
     */
    public static function palpites_por_jogo_pre_jogo(array $palpites): array
    {
        $jogos = [];

        foreach ($palpites as $palpite) {
            if ($palpite['confrontos_ao_vivo_id'] === null && ($palpite['especiais_id'] ?? null) === null && $palpite['confrontos_id'] !== null) {
                $jogos[$palpite['confrontos_id']][] = $palpite;
            }
        }

        return $jogos;
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
        if (($palpite['especial'] ?? null) !== null) {
            return mb_strtoupper("Vencedor: {$palpite['especial']->nome}");
        }

        $jogo = $palpite['ao_vivo'] ?? $palpite['confronto'];

        return $jogo === null ? 'informado' : mb_strtoupper("{$jogo->time_casa} x {$jogo->time_fora}");
    }

    /**
     * Jogo repetido na aposta: só no pré-jogo, onde os palpites formam um bloco Criar Aposta
     * (conferido em conferir_blocos); no ao vivo o Criar Aposta não existe (spec 008, FR-016).
     *
     * @param  list<array<string, mixed>>  $palpites
     *
     * @throws RegraApostaException
     */
    private function conferir_repetidos(array $palpites): void
    {
        $por_confronto = [];

        foreach ($palpites as $palpite) {
            if ($palpite['confrontos_id'] !== null) {
                $por_confronto[$palpite['confrontos_id']][] = $palpite['confrontos_ao_vivo_id'] !== null;
            }
        }

        foreach ($por_confronto as $ao_vivo) {
            if (count($ao_vivo) < 2 || ! in_array(true, $ao_vivo, true)) {
                continue;
            }

            throw new RegraApostaException(in_array(false, $ao_vivo, true)
                ? 'Não é possível cadastrar jogos repetidos na aposta.'
                : 'O Criar Aposta não está disponível para jogos ao vivo.');
        }
    }

    /**
     * Dois palpites do mesmo grupo de mercado, ou de grupos redundantes, não entram no mesmo bloco.
     *
     * @param  list<array<string, mixed>>  $do_jogo
     *
     * @throws RegraApostaException
     */
    private function conferir_combinacoes(array $do_jogo): void
    {
        $grupos = array_map(
            fn (array $palpite) => MercadosCotacao::grupo($palpite['codigo_cotacao'], $palpite['jogador']?->nome),
            $do_jogo,
        );

        foreach ($grupos as $indice => $grupo) {
            foreach (array_slice($grupos, $indice + 1) as $outro) {
                if ($grupo === $outro || MercadosCotacao::redundantes($grupo, $outro)) {
                    throw new RegraApostaException('Não é possível combinar dois palpites do mesmo mercado no Criar Aposta.');
                }
            }
        }
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

        if ($palpite['codigo_cotacao'] === CodigosCotacao::ESPECIAL) {
            return $this->motivo_especial($apostador, $palpite);
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
     * Especial: só com outros esportes liberados (como na barra de esportes), categoria ativa,
     * aguardando e antes da data limite, e opção ativa (spec 006, FR-017).
     *
     * @param  array<string, mixed>  $palpite
     */
    private function motivo_especial(Apostador $apostador, array $palpite): ?string
    {
        $especial = $palpite['especial'];
        $opcao = $palpite['opcao_especial'];

        if (! ($this->exibicao->configuracao($apostador->publico)?->apostar_outros_esportes ?? true)) {
            return 'Apostas em especiais não estão disponíveis.';
        }

        if ($especial === null || $especial->trashed() || ! $especial->aceita_palpites()) {
            return 'A categoria especial '.($especial?->nome ?? 'informada').' não aceita mais palpites.';
        }

        if ($opcao->trashed() || ! $opcao->ativo) {
            return "A opção {$opcao->nome} não está disponível.";
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
