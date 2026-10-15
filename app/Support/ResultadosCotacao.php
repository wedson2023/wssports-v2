<?php

namespace App\Support;

use App\Enums\ResultadoAposta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Regras de resultado dos 323 códigos de cotação (spec 009, R-04): cada código aponta para uma
 * regra parametrizada, nos mesmos grupos de MercadosCotacao, e reproduz exatamente o método oddN
 * do OperacaoController do sistema antigo (inclusive odd114 e odd245/odd247, mantidos por decisão do
 * responsável). Os nomes dos mercados estão em NomesCotacoes.
 *
 * Dado que a regra precisa e o provedor não mandou (gols de um tempo, escanteios) deixa o palpite
 * Aguardando (R-03).
 */
class ResultadosCotacao
{
    private const JOGO = PlacarConfronto::JOGO;

    private const T1 = PlacarConfronto::PRIMEIRO_TEMPO;

    private const T2 = PlacarConfronto::SEGUNDO_TEMPO;

    private const CASA = PlacarConfronto::CASA;

    private const FORA = PlacarConfronto::FORA;

    private const EMPATE = 'empate';

    /**
     * Tipos de palpite de jogador (confrontos_jogadores.tipo), sem acento e em minúsculas.
     */
    private const JOGADOR_QUALQUER_MOMENTO = 'qualquer momento';

    private const JOGADOR_PRIMEIRO = 'primeiro';

    private const JOGADOR_ULTIMO = 'ultimo';

    /**
     * @var array<string, list<mixed>>|null
     */
    private static ?array $regras = null;

    public function avaliar(string $codigo, PlacarConfronto $placar): ResultadoAposta
    {
        $regra = self::regras()[$codigo] ?? null;

        if ($regra === null) {
            Log::warning("Apuração: código de cotação sem regra ({$codigo}); palpite segue Aguardando.");

            return ResultadoAposta::Aguardando;
        }

        [$nome, $parametros] = [$regra[0], array_slice($regra, 1)];

        if (! $this->tem_dados($nome, $parametros, $placar)) {
            return ResultadoAposta::Aguardando;
        }

        if ($nome === 'empate_anula') {
            return $this->empate_anula($placar, ...$parametros);
        }

        return $this->{$nome}($placar, ...$parametros) ? ResultadoAposta::Vencedor : ResultadoAposta::Perdedor;
    }

    /**
     * Palpite de jogador (R-05): nome comparado sem diferenciar maiúsculas, acentos e espaços nas
     * pontas. Primeiro e último gol pela ordem do gol, como no antigo ("1º Gol" e o de maior
     * ordem), contando os gols sem autor informado.
     */
    public function avaliar_jogador(string $nome, string $tipo, PlacarConfronto $placar): ResultadoAposta
    {
        $autores = collect($placar->autores());
        $alvo = $this->normalizar($nome);
        $marcou = fn (?array $gol) => $gol !== null && $gol['nome'] !== null && $this->normalizar($gol['nome']) === $alvo;

        $acertou = match ($this->normalizar($tipo)) {
            self::JOGADOR_QUALQUER_MOMENTO => $autores->contains($marcou),
            self::JOGADOR_PRIMEIRO => $marcou($autores->firstWhere('ordem', 1)),
            self::JOGADOR_ULTIMO => $marcou($autores->sortBy('ordem')->last()),
            default => null,
        };

        if ($acertou === null) {
            Log::warning("Apuração: tipo de palpite de jogador desconhecido ({$tipo}); palpite segue Aguardando.");

            return ResultadoAposta::Aguardando;
        }

        return $acertou ? ResultadoAposta::Vencedor : ResultadoAposta::Perdedor;
    }

    /**
     * @param  list<mixed>  $parametros
     */
    private function tem_dados(string $nome, array $parametros, PlacarConfronto $placar): bool
    {
        return match ($nome) {
            'escanteios', 'escanteios_faixa' => $placar->tem_escanteios(),
            'ambas_tempos', 'tempo_mais_gols', 'time_tempo_mais_gols' => $placar->tem(self::T1) && $placar->tem(self::T2),
            'intervalo_final' => $placar->tem(self::T1),
            'vencedor', 'ambas_marcam', 'handicap', 'placar_exato', 'total_gols', 'par_impar', 'vencedor_ambas' => $placar->tem($parametros[0]),
            default => $placar->tem(self::JOGO),
        };
    }

    // ---------------------------------------------------------------------------------------------
    // Regras
    // ---------------------------------------------------------------------------------------------

    /**
     * Quem venceu o período: casa, empate ou fora.
     */
    private function lado(PlacarConfronto $placar, string $periodo): string
    {
        return match ($placar->gols($periodo, self::CASA) <=> $placar->gols($periodo, self::FORA)) {
            1 => self::CASA,
            0 => self::EMPATE,
            -1 => self::FORA,
        };
    }

    /**
     * Vencedor e dupla chance: o resultado do período está entre os lados escolhidos.
     *
     * @param  list<string>  $lados
     */
    private function vencedor(PlacarConfronto $placar, string $periodo, array $lados): bool
    {
        return in_array($this->lado($placar, $periodo), $lados, true);
    }

    private function ambas_marcam(PlacarConfronto $placar, string $periodo, bool $sim): bool
    {
        $ambas = $placar->gols($periodo, self::CASA) > 0 && $placar->gols($periodo, self::FORA) > 0;

        return $ambas === $sim;
    }

    private function ambas_tempos(PlacarConfronto $placar, bool $primeiro, bool $segundo): bool
    {
        return $this->ambas_marcam($placar, self::T1, $primeiro) && $this->ambas_marcam($placar, self::T2, $segundo);
    }

    /**
     * Handicap asiático com linha em décimos (-45 = -4.5): gols do lado × 10 + linha > gols do outro × 10.
     */
    private function handicap(PlacarConfronto $placar, string $periodo, string $lado, int $linha_decimos): bool
    {
        $outro = $lado === self::CASA ? self::FORA : self::CASA;

        return $placar->gols($periodo, $lado) * 10 + $linha_decimos > $placar->gols($periodo, $outro) * 10;
    }

    private function intervalo_final(PlacarConfronto $placar, string $lado_intervalo, string $lado_final): bool
    {
        return $this->lado($placar, self::T1) === $lado_intervalo && $this->lado($placar, self::JOGO) === $lado_final;
    }

    private function placar_exato(PlacarConfronto $placar, string $periodo, int $casa, int $fora): bool
    {
        return $placar->gols($periodo, self::CASA) === $casa && $placar->gols($periodo, self::FORA) === $fora;
    }

    private function total_gols(PlacarConfronto $placar, string $periodo, string $operador, int $valor): bool
    {
        return $this->comparar($placar->total($periodo), $operador, $valor);
    }

    private function par_impar(PlacarConfronto $placar, string $periodo, bool $par): bool
    {
        return ($placar->total($periodo) % 2 === 0) === $par;
    }

    /**
     * Par ou ímpar dos gols de um time: no antigo, 0 gol não conta como par (odd245/odd247).
     */
    private function par_impar_time(PlacarConfronto $placar, string $lado, bool $par): bool
    {
        $gols = $placar->gols(self::JOGO, $lado);

        return $par ? $gols !== 0 && $gols % 2 === 0 : $gols % 2 === 1;
    }

    private function vencedor_ambas(PlacarConfronto $placar, string $periodo, string $lado, bool $ambas): bool
    {
        return $this->lado($placar, $periodo) === $lado && $this->ambas_marcam($placar, $periodo, $ambas);
    }

    private function vencedor_total(PlacarConfronto $placar, string $lado, string $operador, int $valor): bool
    {
        return $this->lado($placar, self::JOGO) === $lado && $this->total_gols($placar, self::JOGO, $operador, $valor);
    }

    private function empate_anula(PlacarConfronto $placar, string $lado): ResultadoAposta
    {
        return match ($this->lado($placar, self::JOGO)) {
            $lado => ResultadoAposta::Vencedor,
            self::EMPATE => ResultadoAposta::Devolvido,
            default => ResultadoAposta::Perdedor,
        };
    }

    private function escanteios(PlacarConfronto $placar, string $operador, int $valor): bool
    {
        return $this->comparar($placar->escanteios(), $operador, $valor);
    }

    private function escanteios_faixa(PlacarConfronto $placar, int $minimo, int $maximo): bool
    {
        return $placar->escanteios() >= $minimo && $placar->escanteios() <= $maximo;
    }

    /**
     * Compara o total de gols do 1º tempo com o do 2º.
     */
    private function tempo_mais_gols(PlacarConfronto $placar, string $operador): bool
    {
        return $this->comparar($placar->total(self::T1), $operador, $placar->total(self::T2));
    }

    /**
     * Compara os gols de um time no 1º tempo com os dele no 2º.
     */
    private function time_tempo_mais_gols(PlacarConfronto $placar, string $lado, string $operador): bool
    {
        return $this->comparar($placar->gols(self::T1, $lado), $operador, $placar->gols(self::T2, $lado));
    }

    /**
     * O time não sofre gol (sim) ou sofre (não).
     */
    private function sem_sofrer_gol(PlacarConfronto $placar, string $lado, bool $sim): bool
    {
        $outro = $lado === self::CASA ? self::FORA : self::CASA;

        return ($placar->gols(self::JOGO, $outro) === 0) === $sim;
    }

    /**
     * Diferença de gols a favor do lado (gols dele − gols do outro).
     */
    private function margem(PlacarConfronto $placar, string $lado, string $operador, int $valor): bool
    {
        $outro = $lado === self::CASA ? self::FORA : self::CASA;

        return $this->comparar($placar->gols(self::JOGO, $lado) - $placar->gols(self::JOGO, $outro), $operador, $valor);
    }

    /**
     * Empate com gols (com_gols) ou jogo sem gols.
     */
    private function margem_empate(PlacarConfronto $placar, bool $com_gols): bool
    {
        return $this->lado($placar, self::JOGO) === self::EMPATE && ($placar->total(self::JOGO) > 0) === $com_gols;
    }

    private function total_gols_time(PlacarConfronto $placar, string $lado, string $operador, int $valor): bool
    {
        return $this->comparar($placar->gols(self::JOGO, $lado), $operador, $valor);
    }

    private function comparar(int $a, string $operador, int $b): bool
    {
        return match ($operador) {
            '=' => $a === $b,
            '>' => $a > $b,
            '<' => $a < $b,
            '>=' => $a >= $b,
            '<=' => $a <= $b,
        };
    }

    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }

    // ---------------------------------------------------------------------------------------------
    // Tabela código → [regra, parâmetros...]
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, list<mixed>>
     */
    private static function regras(): array
    {
        return self::$regras ??= self::montar();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function montar(): array
    {
        $r = [];
        $lados = [self::CASA, self::EMPATE, self::FORA];

        // vencedor (odd1-3), vencedor do 1º tempo (odd129-131) e do 2º tempo (odd132-134)
        foreach ([self::JOGO => 1, self::T1 => 129, self::T2 => 132] as $periodo => $inicio) {
            foreach ($lados as $i => $lado) {
                $r['odd'.($inicio + $i)] = ['vencedor', $periodo, [$lado]];
            }
        }

        // ambas marcam (odd4-6) e ambas não marcam (odd7-9): jogo, 1º e 2º tempo
        foreach ([self::JOGO, self::T1, self::T2] as $i => $periodo) {
            $r['odd'.(4 + $i)] = ['ambas_marcam', $periodo, true];
            $r['odd'.(7 + $i)] = ['ambas_marcam', $periodo, false];
        }

        // dupla chance
        $r['odd10'] = ['vencedor', self::JOGO, [self::CASA, self::EMPATE]];
        $r['odd11'] = ['vencedor', self::JOGO, [self::CASA, self::FORA]];
        $r['odd12'] = ['vencedor', self::T1, [self::CASA, self::FORA]];
        $r['odd13'] = ['vencedor', self::JOGO, [self::EMPATE, self::FORA]];
        $r['odd14'] = ['vencedor', self::T1, [self::EMPATE, self::FORA]];

        // handicap asiático do jogo, linhas -4.5 a +4.5 (os códigos do provedor não são sequenciais)
        $linhas = [-45, -35, -25, -15, -5, 5, 15, 25, 35, 45];
        $codigos_casa = [147, 148, 16, 15, 149, 150, 151, 152, 153, 154];
        $codigos_fora = [155, 156, 157, 17, 158, 159, 160, 161, 162, 163];

        foreach ($linhas as $i => $linha) {
            $r["odd{$codigos_casa[$i]}"] = ['handicap', self::JOGO, self::CASA, $linha];
            $r["odd{$codigos_fora[$i]}"] = ['handicap', self::JOGO, self::FORA, $linha];
            // handicap asiático do 1º tempo: casa odd225-234, fora odd235-244
            $r['odd'.(225 + $i)] = ['handicap', self::T1, self::CASA, $linha];
            $r['odd'.(235 + $i)] = ['handicap', self::T1, self::FORA, $linha];
        }

        // intervalo / final (odd18-26): casa|casa, casa|empate ... fora|fora
        $codigo = 18;

        foreach ($lados as $intervalo) {
            foreach ($lados as $final) {
                $r['odd'.$codigo++] = ['intervalo_final', $intervalo, $final];
            }
        }

        // placar exato do jogo (odd27-60) e do 1º tempo (odd61-84), na ordem do provedor
        $placares = [
            self::JOGO => [27, [[0, 5], [1, 5], [2, 5], [3, 4], [4, 4], [5, 3], [6, 1]]],
            self::T1 => [61, [[0, 4], [1, 5], [2, 5], [3, 4], [4, 1]]],
        ];

        foreach ($placares as $periodo => [$codigo, $faixas]) {
            foreach ($faixas as [$casa, $fora_maximo]) {
                foreach (range(0, $fora_maximo) as $fora) {
                    $r['odd'.$codigo++] = ['placar_exato', $periodo, $casa, $fora];
                }
            }
        }

        // total de gols na partida: exatamente 1 a 6 (odd85-90) e 7 ou mais (odd91)
        foreach (range(1, 6) as $i => $gols) {
            $r['odd'.(85 + $i)] = ['total_gols', self::JOGO, '=', $gols];
        }

        $r['odd91'] = ['total_gols', self::JOGO, '>=', 7];

        // total de gols do 1º tempo (odd92-102)
        $r['odd92'] = ['total_gols', self::T1, '>=', 2];
        $r['odd93'] = ['total_gols', self::T1, '>=', 3];
        $r['odd94'] = ['total_gols', self::T1, '>=', 4];
        $r['odd95'] = ['total_gols', self::T1, '=', 0];
        $r['odd96'] = ['total_gols', self::T1, '<=', 1];
        $r['odd97'] = ['total_gols', self::T1, '=', 0];
        $r['odd98'] = ['total_gols', self::T1, '=', 1];
        $r['odd99'] = ['total_gols', self::T1, '=', 2];
        $r['odd100'] = ['total_gols', self::T1, '=', 3];
        $r['odd101'] = ['total_gols', self::T1, '=', 4];
        $r['odd102'] = ['total_gols', self::T1, '>=', 5];

        // total de gols do 2º tempo (odd103-114); odd114 ("mais de 3") é = 3 como no antigo
        foreach (range(1, 5) as $i => $gols) {
            $r['odd'.(103 + $i)] = ['total_gols', self::T2, '>=', $gols];
        }

        $r['odd108'] = ['total_gols', self::T2, '=', 0];
        $r['odd109'] = ['total_gols', self::T2, '<=', 1];
        $r['odd110'] = ['total_gols', self::T2, '<=', 2];
        $r['odd111'] = ['total_gols', self::T2, '=', 0];
        $r['odd112'] = ['total_gols', self::T2, '=', 1];
        $r['odd113'] = ['total_gols', self::T2, '=', 2];
        $r['odd114'] = ['total_gols', self::T2, '=', 3];

        // mais/menos gols na partida: +1.5 a +6.5 (odd115-120), -0.5 a -3.5 (odd121-124)
        foreach (range(2, 7) as $i => $gols) {
            $r['odd'.(115 + $i)] = ['total_gols', self::JOGO, '>=', $gols];
        }

        $r['odd121'] = ['total_gols', self::JOGO, '=', 0];
        $r['odd122'] = ['total_gols', self::JOGO, '<=', 1];
        $r['odd123'] = ['total_gols', self::JOGO, '<=', 2];
        $r['odd124'] = ['total_gols', self::JOGO, '<=', 3];

        // par/ímpar do total: jogo (odd125 ímpar, odd127 par), 1º tempo (odd126, odd128), 2º tempo (odd249, odd250)
        $r['odd125'] = ['par_impar', self::JOGO, false];
        $r['odd126'] = ['par_impar', self::T1, false];
        $r['odd127'] = ['par_impar', self::JOGO, true];
        $r['odd128'] = ['par_impar', self::T1, true];
        $r['odd249'] = ['par_impar', self::T2, false];
        $r['odd250'] = ['par_impar', self::T2, true];

        // vencedor + ambas marcam: jogo (odd135-140) e 1º tempo (odd255-260)
        foreach ([self::JOGO => 135, self::T1 => 255] as $periodo => $codigo) {
            foreach ($lados as $lado) {
                $r['odd'.$codigo++] = ['vencedor_ambas', $periodo, $lado, true];
                $r['odd'.$codigo++] = ['vencedor_ambas', $periodo, $lado, false];
            }
        }

        // resultado + total de gols acima/abaixo de 2.5 (odd141-146)
        $codigo = 141;

        foreach ($lados as $lado) {
            $r['odd'.$codigo++] = ['vencedor_total', $lado, '>=', 3];
            $r['odd'.$codigo++] = ['vencedor_total', $lado, '<=', 2];
        }

        // empate anula aposta: empate devolve o palpite (odd164 casa, odd165 fora)
        $r['odd164'] = ['empate_anula', self::CASA];
        $r['odd165'] = ['empate_anula', self::FORA];

        // escanteios: acima de 2 a 19 (odd166-183), abaixo de 2 a 19 (odd184-201), exatos 2 a 19 (odd202-219)
        foreach (range(2, 19) as $i => $escanteios) {
            $r['odd'.(166 + $i)] = ['escanteios', '>', $escanteios];
            $r['odd'.(184 + $i)] = ['escanteios', '<', $escanteios];
            $r['odd'.(202 + $i)] = ['escanteios', '=', $escanteios];
        }

        // total de escanteios por faixa (odd220-224)
        $r['odd220'] = ['escanteios_faixa', 12, 14];
        $r['odd221'] = ['escanteios_faixa', 9, 11];
        $r['odd222'] = ['escanteios_faixa', 6, 8];
        $r['odd223'] = ['escanteios', '>', 14];
        $r['odd224'] = ['escanteios', '<', 6];

        // time par/ímpar (odd245-248): 0 gol não conta como par, como no antigo
        $r['odd245'] = ['par_impar_time', self::CASA, true];
        $r['odd246'] = ['par_impar_time', self::CASA, false];
        $r['odd247'] = ['par_impar_time', self::FORA, true];
        $r['odd248'] = ['par_impar_time', self::FORA, false];

        // ambas marcam no 1º/2º tempo (odd251-254): sim/sim, sim/não, não/sim, não/não
        $r['odd251'] = ['ambas_tempos', true, true];
        $r['odd252'] = ['ambas_tempos', true, false];
        $r['odd253'] = ['ambas_tempos', false, true];
        $r['odd254'] = ['ambas_tempos', false, false];

        // tempo com mais gols (odd261-263) e time + tempo com mais gols (odd264-269)
        $r['odd261'] = ['tempo_mais_gols', '>'];
        $r['odd262'] = ['tempo_mais_gols', '<'];
        $r['odd263'] = ['tempo_mais_gols', '='];
        $r['odd264'] = ['time_tempo_mais_gols', self::CASA, '>'];
        $r['odd265'] = ['time_tempo_mais_gols', self::CASA, '<'];
        $r['odd266'] = ['time_tempo_mais_gols', self::CASA, '='];
        $r['odd267'] = ['time_tempo_mais_gols', self::FORA, '>'];
        $r['odd268'] = ['time_tempo_mais_gols', self::FORA, '<'];
        $r['odd269'] = ['time_tempo_mais_gols', self::FORA, '='];

        // time sem sofrer gol (odd270-273): casa sim/não, fora sim/não
        $r['odd270'] = ['sem_sofrer_gol', self::CASA, true];
        $r['odd271'] = ['sem_sofrer_gol', self::CASA, false];
        $r['odd272'] = ['sem_sofrer_gol', self::FORA, true];
        $r['odd273'] = ['sem_sofrer_gol', self::FORA, false];

        // margem de vitória (odd274-283): casa 1, 2, 3, 4+; visitante 1, 2, 3, 4+; empate com gols; sem gols
        foreach ([self::CASA => 274, self::FORA => 278] as $lado => $inicio) {
            foreach ([1, 2, 3] as $i => $margem) {
                $r['odd'.($inicio + $i)] = ['margem', $lado, '=', $margem];
            }

            $r['odd'.($inicio + 3)] = ['margem', $lado, '>=', 4];
        }

        $r['odd282'] = ['margem_empate', true];
        $r['odd283'] = ['margem_empate', false];

        // total de gols do time: casa +0.5 a +9.5 (odd284-293), -0.5 a -9.5 (odd294-303); fora odd304-323
        foreach ([self::CASA => 284, self::FORA => 304] as $lado => $inicio) {
            foreach (range(0, 9) as $i) {
                $r['odd'.($inicio + $i)] = ['total_gols_time', $lado, '>=', $i + 1];
                $r['odd'.($inicio + 10 + $i)] = ['total_gols_time', $lado, '<=', $i];
            }
        }

        return $r;
    }
}
