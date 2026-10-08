<?php

namespace App\Services;

use App\Models\Configuracoes;
use App\Support\NomesCotacoes;
use Carbon\CarbonTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Detalhe de um confronto com todas as cotações disponíveis e os jogadores (FR-065a, FR-065b),
 * pelo mesmo cálculo e pelas mesmas regras de exibição da listagem e da aposta.
 */
class DetalheConfrontos
{
    private const NAO_ENCONTRADO = 'Confronto não encontrado.';

    public function __construct(private RegrasExibicao $regras, private CalculoCotacoes $calculo) {}

    /**
     * @return array<string, mixed>
     */
    public function pre_jogo(Publico $publico, int $id, CarbonTimeZone $fuso): array
    {
        $confronto = $this->regras->consulta_pre_jogo($publico)
            ->where('co.id', $id)
            ->first(['co.*', 'ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira']);

        abort_unless($confronto !== null && $this->regras->esporte_permitido($publico, $confronto->esporte), 404, self::NAO_ENCONTRADO);

        $item = $this->item($confronto, (int) $confronto->id);

        return [
            ...$this->dados($publico, $confronto, CalculoCotacoes::PRE_JOGO, $fuso),
            'cotacoes' => $this->cotacoes($publico, CalculoCotacoes::PRE_JOGO, $item, travado: false),
            'jogadores' => $this->regras->pode_apostar_jogadores($publico) ? $this->jogadores($publico, $confronto) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ao_vivo(Publico $publico, int $id, CarbonTimeZone $fuso): array
    {
        $this->regras->garantir_ao_vivo_habilitado($publico);

        $jogo = $this->regras->consulta_ao_vivo($publico)
            ->where('av.id', $id)
            ->first(['av.*', 'ca.nome as campeonato_nome', 'ca.pais', 'ca.bandeira']);

        abort_unless($jogo !== null && $this->regras->esporte_permitido($publico, $jogo->esporte), 404, self::NAO_ENCONTRADO);

        $configuracoes = Configuracoes::atual();
        // travado: trava geral ou sem atualização há mais tempo que o limite; nunca valor antigo
        $travado = $configuracoes->ao_vivo_travado
            || Carbon::parse($jogo->ultima_atualizacao_em, 'UTC')->lt(now()->subSeconds($configuracoes->segundos_trava_ao_vivo));

        return [
            ...$this->dados($publico, $jogo, CalculoCotacoes::AO_VIVO, $fuso),
            'placar_casa' => (int) $jogo->placar_casa,
            'placar_fora' => (int) $jogo->placar_fora,
            'minuto' => (int) $jogo->minuto,
            'cronometro' => $jogo->cronometro,
            'situacao' => $jogo->situacao,
            'travado' => $travado,
            'cotacoes' => $this->cotacoes($publico, CalculoCotacoes::AO_VIVO, $this->item($jogo, (int) $jogo->confrontos_id), $travado),
            'jogadores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Publico $publico, object $jogo, string $tipo, CarbonTimeZone $fuso): array
    {
        return [
            'token_recusado' => $publico->token_recusado,
            'id' => (int) $jogo->id,
            'tipo' => $tipo,
            'campeonato' => $jogo->campeonato_nome,
            'pais' => $jogo->pais,
            'bandeira' => $jogo->bandeira,
            'time_casa' => $jogo->time_casa,
            'escudo_casa' => $jogo->escudo_casa,
            'time_fora' => $jogo->time_fora,
            'escudo_fora' => $jogo->escudo_fora,
            'esporte' => $jogo->esporte,
            'data_inicio' => Carbon::parse($jogo->data_inicio, 'UTC')->setTimezone($fuso)->toIso8601String(),
        ];
    }

    private function item(object $jogo, int $confrontos_id): object
    {
        return (object) [
            'id' => (int) $jogo->id,
            'campeonatos_id' => (int) $jogo->campeonatos_id,
            'confrontos_id' => $confrontos_id,
            'cotacoes' => json_decode($jogo->cotacoes, true) ?? [],
        ];
    }

    /**
     * Todas as cotações disponíveis (> 0) ajustadas para o público, na ordem dos códigos.
     *
     * @return list<array{codigo_cotacao: string, mercado: string, cotacao: string}>
     */
    private function cotacoes(Publico $publico, string $tipo, object $item, bool $travado): array
    {
        $codigos = array_keys(array_filter($item->cotacoes, fn ($cotacao) => (float) $cotacao > 0));
        usort($codigos, fn (string $a, string $b) => (int) substr($a, 3) <=> (int) substr($b, 3));

        $ajustadas = $this->calculo->ajustar($publico, $tipo, collect([$item]), $codigos)[$item->id] ?? [];
        $cotacoes = [];

        foreach ($codigos as $codigo) {
            $cotacao = $travado ? 0.0 : ($ajustadas[$codigo] ?? 0.0);

            if ($cotacao > 0 || $travado) {
                $cotacoes[] = [
                    'codigo_cotacao' => $codigo,
                    'mercado' => NomesCotacoes::nome($codigo),
                    'cotacao' => number_format($cotacao, 2, '.', ''),
                ];
            }
        }

        return $cotacoes;
    }

    /**
     * Jogadores do confronto com a cotação ajustada para o público (só os disponíveis).
     *
     * @return list<array<string, mixed>>
     */
    private function jogadores(Publico $publico, object $confronto): array
    {
        $jogadores = DB::table('confrontos_jogadores')
            ->where('confrontos_id', $confronto->id)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get(['id', 'nome', 'opcao', 'tipo', 'odd'])
            ->each(fn (object $jogador) => $jogador->campeonatos_id = $confronto->campeonatos_id);

        $ajustadas = $this->calculo->ajustar_jogadores($publico, $jogadores);

        return $jogadores
            ->filter(fn (object $jogador) => ($ajustadas[$jogador->id] ?? 0) > 0)
            ->map(fn (object $jogador) => [
                'confrontos_jogadores_id' => (int) $jogador->id,
                'nome' => $jogador->nome,
                'opcao' => $jogador->opcao,
                'tipo' => $jogador->tipo,
                'cotacao' => number_format($ajustadas[$jogador->id], 2, '.', ''),
            ])
            ->values()
            ->all();
    }
}
