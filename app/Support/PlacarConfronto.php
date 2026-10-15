<?php

namespace App\Support;

use App\Enums\SituacaoAoVivo;
use App\Models\ConfrontosGols;
use App\Models\ConfrontosResultados;
use Illuminate\Support\Collection;

/**
 * Resultado de um jogo, só para leitura, usado na avaliação dos mercados (spec 009, R-04). Gols do
 * 1º e do 2º tempo são os marcados só naquele tempo; null = o provedor não informou.
 */
final class PlacarConfronto
{
    public const JOGO = 'jogo';

    public const PRIMEIRO_TEMPO = '1t';

    public const SEGUNDO_TEMPO = '2t';

    public const CASA = 'casa';

    public const FORA = 'fora';

    /**
     * @param  array<string, array{casa: ?int, fora: ?int}>  $gols_periodo  gols de cada time por período
     * @param  list<array{ordem: int, nome: ?string}>  $autores  gols por jogador, na ordem do jogo (nome nulo = autor não informado)
     */
    private function __construct(
        private array $gols_periodo,
        private ?int $escanteios_casa,
        private ?int $escanteios_fora,
        private array $autores,
    ) {}

    /**
     * @param  Collection<int, ConfrontosGols>  $gols
     */
    public static function de(ConfrontosResultados $resultado, Collection $gols): self
    {
        return new self(
            [
                self::JOGO => ['casa' => $resultado->placar_casa, 'fora' => $resultado->placar_fora],
                self::PRIMEIRO_TEMPO => ['casa' => $resultado->gols_primeiro_tempo_casa, 'fora' => $resultado->gols_primeiro_tempo_fora],
                self::SEGUNDO_TEMPO => ['casa' => $resultado->gols_segundo_tempo_casa, 'fora' => $resultado->gols_segundo_tempo_fora],
            ],
            $resultado->escanteios_casa,
            $resultado->escanteios_fora,
            $gols->sortBy('ordem')->map(fn (ConfrontosGols $gol) => ['ordem' => $gol->ordem, 'nome' => $gol->nome])->values()->all(),
        );
    }

    /**
     * Placar atual de um jogo do ao vivo (linha de confrontos_ao_vivo), para os selos "ganhando
     * agora" e "perdendo agora" do bilhete (spec 010, R-06). O ao vivo não traz o autor dos gols;
     * no 1º tempo, os gols do 2º tempo ainda não informados contam como 0.
     */
    public static function do_ao_vivo(object $jogo): self
    {
        $numero = fn (mixed $valor) => $valor === null ? null : (int) $valor;
        // a linha pode vir do query builder (texto) ou do model (enum)
        $situacao = $jogo->situacao instanceof SituacaoAoVivo ? $jogo->situacao->value : (string) $jogo->situacao;
        $primeiro_tempo = $situacao === SituacaoAoVivo::PrimeiroTempo->value;
        $segundo_tempo = fn (mixed $valor) => $valor === null && $primeiro_tempo ? 0 : $numero($valor);

        return new self(
            [
                self::JOGO => ['casa' => (int) $jogo->placar_casa, 'fora' => (int) $jogo->placar_fora],
                self::PRIMEIRO_TEMPO => ['casa' => $numero($jogo->gols_primeiro_tempo_casa), 'fora' => $numero($jogo->gols_primeiro_tempo_fora)],
                self::SEGUNDO_TEMPO => ['casa' => $segundo_tempo($jogo->gols_segundo_tempo_casa), 'fora' => $segundo_tempo($jogo->gols_segundo_tempo_fora)],
            ],
            $numero($jogo->escanteios_casa),
            $numero($jogo->escanteios_fora),
            [],
        );
    }

    /**
     * O período tem os gols dos dois times informados.
     */
    public function tem(string $periodo): bool
    {
        return $this->gols_periodo[$periodo]['casa'] !== null && $this->gols_periodo[$periodo]['fora'] !== null;
    }

    public function tem_escanteios(): bool
    {
        return $this->escanteios_casa !== null && $this->escanteios_fora !== null;
    }

    public function gols(string $periodo, string $lado): int
    {
        return (int) $this->gols_periodo[$periodo][$lado];
    }

    public function total(string $periodo): int
    {
        return $this->gols($periodo, self::CASA) + $this->gols($periodo, self::FORA);
    }

    public function escanteios(): int
    {
        return (int) $this->escanteios_casa + (int) $this->escanteios_fora;
    }

    /**
     * @return list<array{ordem: int, nome: ?string}>
     */
    public function autores(): array
    {
        return $this->autores;
    }
}
