<?php

namespace App\Services;

use App\Enums\SituacaoAoVivo;
use App\Enums\TipoEventoConfronto;
use App\Events\ConfrontoAtualizado;
use App\Jobs\ConferirEncerramentosAutomaticos;

/**
 * Pós-processamento da carga do ao vivo (spec 010, R-16): compara cada jogo gravado com o estado
 * anterior e avisa o bilhete em tempo real do que mudou. Nada aqui pode desfazer a carga: quem chama
 * trata as falhas.
 */
class ProcessamentoAoVivo
{
    /**
     * Campos que, quando mudam, atualizam o jogo na tela.
     */
    private const CAMPOS_TELA = [
        'placar_casa',
        'placar_fora',
        'gols_primeiro_tempo_casa',
        'gols_primeiro_tempo_fora',
        'gols_segundo_tempo_casa',
        'gols_segundo_tempo_fora',
        'escanteios_casa',
        'escanteios_fora',
        'minuto',
        'situacao',
    ];

    public function __construct(private EventosConfrontos $eventos) {}

    /**
     * @param  array<int, array<string, mixed>>  $antes  estado anterior por codigo_externo (com a coluna cotacoes em JSON)
     * @param  list<array<string, mixed>>  $depois  linhas gravadas pela carga
     */
    public function processar(array $antes, array $depois): void
    {
        $alterados = [];

        foreach ($depois as $jogo) {
            // jogo do ao vivo sem confronto na grade não tem aposta nem bilhete
            if ($jogo['confrontos_id'] === null) {
                continue;
            }

            $anterior = $antes[$jogo['codigo_externo']] ?? null;
            $mudancas = $this->mudancas($anterior, $jogo);

            if ($anterior !== null) {
                $this->detectar_eventos($anterior, $jogo);
            }

            if ($mudancas['tela'] || $mudancas['cotacoes']) {
                $alterados[] = (int) $jogo['confrontos_id'];

                ConfrontoAtualizado::emitir(ConfrontoAtualizado::de_jogo(
                    (int) $jogo['confrontos_id'],
                    $jogo,
                    (string) $jogo['esporte'],
                    (string) $jogo['situacao'],
                    (int) $jogo['minuto'],
                    $mudancas['gol'],
                    $mudancas['cotacoes'],
                ));
            }
        }

        // a oferta muda com as cotações e o placar: confere os encerramentos automáticos (R-12)
        ConferirEncerramentosAutomaticos::despachar_se_houver($alterados);
    }

    /**
     * Gol (placar total subiu), gol anulado (desceu) e início do intervalo (FR-042), um evento de
     * cada tipo por carga; o fim de jogo vem da carga de resultados (R-16).
     *
     * @param  array<string, mixed>  $anterior
     * @param  array<string, mixed>  $atual
     */
    private function detectar_eventos(array $anterior, array $atual): void
    {
        $total_antes = (int) $anterior['placar_casa'] + (int) $anterior['placar_fora'];
        $total_agora = (int) $atual['placar_casa'] + (int) $atual['placar_fora'];
        $registrar = fn (TipoEventoConfronto $tipo) => $this->eventos->registrar_e_avisar(
            (int) $atual['confrontos_id'],
            isset($anterior['id']) ? (int) $anterior['id'] : null,
            $tipo,
            (int) $atual['placar_casa'],
            (int) $atual['placar_fora'],
            (int) $atual['minuto'],
        );

        if ($total_agora > $total_antes) {
            $registrar(TipoEventoConfronto::Gol);
        } elseif ($total_agora < $total_antes) {
            $registrar(TipoEventoConfronto::GolAnulado);
        }

        if ($atual['situacao'] === SituacaoAoVivo::Intervalo->value && $anterior['situacao'] !== SituacaoAoVivo::Intervalo->value) {
            $registrar(TipoEventoConfronto::Intervalo);
        }
    }

    /**
     * O que mudou no jogo desde a carga anterior; jogo novo no ao vivo conta como mudança.
     *
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>  $atual
     * @return array{tela: bool, cotacoes: bool, gol: bool}
     */
    private function mudancas(?array $anterior, array $atual): array
    {
        if ($anterior === null) {
            return ['tela' => true, 'cotacoes' => true, 'gol' => false];
        }

        $tela = false;

        foreach (self::CAMPOS_TELA as $campo) {
            if ((string) ($anterior[$campo] ?? '') !== (string) ($atual[$campo] ?? '')) {
                $tela = true;
                break;
            }
        }

        $gol = (int) $anterior['placar_casa'] !== (int) $atual['placar_casa']
            || (int) $anterior['placar_fora'] !== (int) $atual['placar_fora'];

        return [
            'tela' => $tela,
            // compara os valores decodificados: o MySQL reordena as chaves do JSON gravado
            'cotacoes' => json_decode((string) $anterior['cotacoes'], true) != json_decode((string) $atual['cotacoes'], true),
            'gol' => $gol,
        ];
    }
}
