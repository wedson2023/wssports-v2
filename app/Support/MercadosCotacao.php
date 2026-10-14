<?php

namespace App\Support;

/**
 * Grupos de mercado dos códigos de cotação e pares de grupos redundantes, usados nas combinações
 * proibidas do Criar Aposta (spec 008, FR-005 e FR-005a). Os grupos são as categorias do detalhe do
 * jogo (resources/js/utils/market_groups.js, iguais às do sistema antigo). É a fonte única da regra:
 * o cupom recebe grupos e pares pelas props da página.
 */
class MercadosCotacao
{
    /**
     * Faixas de códigos (número do "oddN", início e fim) de cada grupo.
     */
    private const FAIXAS = [
        'vencedor' => [[1, 3]],
        'ambas_equipes' => [[4, 4], [7, 7]],
        'dupla_chance' => [[10, 11], [13, 13]],
        'handicap_asiatico' => [[15, 17], [147, 163]],
        'intervalo_final' => [[18, 26]],
        'resultado_exato' => [[27, 60]],
        'gols_mais_menos' => [[115, 124]],
        'gols_par_impar' => [[125, 125], [127, 127]],
        'total_gols' => [[85, 91]],
        'vencedor_ambas_equipes' => [[135, 140]],
        'resultado_total_gols' => [[141, 146]],
        'empate_anula_aposta' => [[164, 165]],
        'escanteios_acima_abaixo' => [[166, 201]],
        'escanteios_exatos' => [[202, 219]],
        'total_escanteios' => [[220, 224]],
        'time_impar_par' => [[245, 248]],
        'ambas_marcam_tempos' => [[251, 254]],
        'tempo_mais_gols' => [[261, 263]],
        'time_tempo_mais_gols' => [[264, 269]],
        'time_sem_sofrer_gol' => [[270, 270], [272, 272]],
        'time_sofre_gol' => [[271, 271], [273, 273]],
        'margem_vitoria' => [[274, 283]],
        'time_total_gols' => [[284, 323]],
        'vencedor_1t' => [[129, 131]],
        'ambas_equipes_1t' => [[5, 5], [8, 8]],
        'dupla_chance_1t' => [[12, 12], [14, 14]],
        'handicap_asiatico_1t' => [[225, 244]],
        'resultado_exato_1t' => [[61, 84]],
        'gols_mais_menos_1t' => [[92, 96]],
        'gols_par_impar_1t' => [[126, 126], [128, 128]],
        'total_gols_1t' => [[97, 102]],
        'vencedor_ambas_equipes_1t' => [[255, 260]],
        'vencedor_2t' => [[132, 134]],
        'ambas_equipes_2t' => [[6, 6], [9, 9]],
        'gols_mais_menos_2t' => [[103, 110]],
        'total_gols_2t' => [[111, 114]],
        'gols_par_impar_2t' => [[249, 250]],
    ];

    /**
     * Pares de grupos em que um mercado já contém o resultado do outro (data-model, seção 5).
     */
    public const PARES_REDUNDANTES = [
        ['vencedor', 'dupla_chance'],
        ['vencedor', 'empate_anula_aposta'],
        ['vencedor', 'vencedor_ambas_equipes'],
        ['vencedor', 'resultado_total_gols'],
        ['vencedor', 'margem_vitoria'],
        ['vencedor', 'intervalo_final'],
        ['vencedor', 'resultado_exato'],
        ['ambas_equipes', 'vencedor_ambas_equipes'],
        ['ambas_equipes', 'resultado_exato'],
        ['gols_mais_menos', 'total_gols'],
        ['gols_mais_menos', 'resultado_total_gols'],
        ['gols_mais_menos', 'resultado_exato'],
        ['resultado_exato', 'dupla_chance'],
        ['resultado_exato', 'empate_anula_aposta'],
        ['resultado_exato', 'vencedor_ambas_equipes'],
        ['resultado_exato', 'resultado_total_gols'],
        ['resultado_exato', 'margem_vitoria'],
        ['resultado_exato', 'intervalo_final'],
        ['resultado_exato', 'total_gols'],
        ['resultado_exato', 'gols_par_impar'],
        ['resultado_exato', 'time_total_gols'],
        ['resultado_exato', 'time_sem_sofrer_gol'],
        ['resultado_exato', 'time_sofre_gol'],
        ['vencedor_1t', 'dupla_chance_1t'],
        ['vencedor_1t', 'vencedor_ambas_equipes_1t'],
        ['vencedor_1t', 'resultado_exato_1t'],
        ['vencedor_1t', 'intervalo_final'],
        ['ambas_equipes_1t', 'vencedor_ambas_equipes_1t'],
        ['ambas_equipes_1t', 'resultado_exato_1t'],
        ['gols_mais_menos_1t', 'total_gols_1t'],
        ['gols_mais_menos_1t', 'resultado_exato_1t'],
        ['resultado_exato_1t', 'dupla_chance_1t'],
        ['resultado_exato_1t', 'vencedor_ambas_equipes_1t'],
        ['resultado_exato_1t', 'total_gols_1t'],
        ['resultado_exato_1t', 'gols_par_impar_1t'],
        ['gols_mais_menos_2t', 'total_gols_2t'],
    ];

    /**
     * @var array<string, string>|null código → grupo, montado uma vez a partir das faixas
     */
    private static ?array $grupos = null;

    /**
     * Grupo do palpite: jogador pelo nome (o mesmo jogador não entra duas vezes, em nenhum tipo);
     * código fora das categorias é um grupo só dele.
     */
    public static function grupo(string $codigo, ?string $nome_jogador = null): string
    {
        if ($codigo === CodigosCotacao::JOGADOR) {
            return 'jogador:'.mb_strtolower(trim((string) $nome_jogador));
        }

        return self::grupos()[$codigo] ?? $codigo;
    }

    public static function redundantes(string $grupo_a, string $grupo_b): bool
    {
        foreach (self::PARES_REDUNDANTES as [$primeiro, $segundo]) {
            if (($grupo_a === $primeiro && $grupo_b === $segundo) || ($grupo_a === $segundo && $grupo_b === $primeiro)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grupo de cada código de cotação (para o cupom).
     *
     * @return array<string, string>
     */
    public static function grupos(): array
    {
        if (self::$grupos !== null) {
            return self::$grupos;
        }

        $grupos = [];

        foreach (self::FAIXAS as $grupo => $faixas) {
            foreach ($faixas as [$inicio, $fim]) {
                foreach (range($inicio, $fim) as $numero) {
                    $grupos["odd{$numero}"] = $grupo;
                }
            }
        }

        return self::$grupos = $grupos;
    }
}
