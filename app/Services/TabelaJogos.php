<?php

namespace App\Services;

use App\Models\Configuracoes;
use App\Models\Usuarios;
use Illuminate\Validation\ValidationException;

/**
 * Tabela de jogos impressa pelo vendedor (spec 006, R-08): os jogos que ele vê na lista, com as
 * cotações dele nas colunas da tabela do sistema antigo.
 */
class TabelaJogos
{
    /**
     * Colunas da tabela, na ordem do sistema antigo: Casa, Empate, Fora, Ambas, +2.5, DPC, CGF,
     * GMC, GMF, 2GMC, N.A, -2.5, DPF e FGC.
     */
    public const CODIGOS = ['odd1', 'odd2', 'odd3', 'odd4', 'odd116', 'odd10', 'odd135', 'odd15', 'odd17', 'odd16', 'odd7', 'odd123', 'odd13', 'odd139'];

    public function __construct(private ListagemConfrontos $listagem, private RegrasExibicao $regras) {}

    /**
     * @param  array<string, mixed>  $filtros  dia, esporte, campeonatos, pagina e por_pagina
     * @return array<string, mixed>
     */
    public function montar(Usuarios $vendedor, array $filtros): array
    {
        $publico = new Publico(Publico::VENDEDOR, usuario: $vendedor);

        if (! $this->regras->esporte_permitido($publico, $filtros['esporte'])) {
            throw ValidationException::withMessages(['esporte' => 'Esporte não permitido.']);
        }

        $listagem = $this->listagem->pre_jogo($publico, [...$filtros, 'codigos_cotacao' => self::CODIGOS]);

        return [
            'nome_sistema' => Configuracoes::atual()->nome_sistema,
            'atualizada_em' => now()->setTimezone('-03:00')->toIso8601String(),
            'campeonatos' => array_map(fn (array $campeonato) => [
                'id' => $campeonato['id'],
                'nome' => $campeonato['nome'],
                'confrontos' => array_map(fn (array $confronto) => [
                    'id' => $confronto['id'],
                    'data_inicio' => $confronto['data_inicio'],
                    'time_casa' => $confronto['time_casa'],
                    'time_fora' => $confronto['time_fora'],
                    'cotacoes' => $this->cotacoes($confronto['cotacoes']),
                ], $campeonato['confrontos']),
            ], $listagem['campeonatos']),
            'meta' => $listagem['meta'],
        ];
    }

    /**
     * Cotações como texto de 2 casas; ausente ou bloqueada sai "1.00", como no antigo.
     *
     * @param  array<string, float>  $cotacoes
     * @return array<string, string>
     */
    private function cotacoes(array $cotacoes): array
    {
        $formatadas = [];

        foreach (self::CODIGOS as $codigo) {
            $valor = (float) ($cotacoes[$codigo] ?? 0);
            $formatadas[$codigo] = number_format(max($valor, 1.0), 2, '.', '');
        }

        return $formatadas;
    }
}
