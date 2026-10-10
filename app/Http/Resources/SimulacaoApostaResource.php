<?php

namespace App\Http\Resources;

use App\Models\Apostas;
use App\Services\RegrasAposta;
use App\Support\CodigosCotacao;
use App\Support\FusoSistema;
use App\Support\NomesCotacoes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Simulação do código do visitante para o vendedor: palpites com as cotações dele, prêmio pelas
 * regras dele e o motivo dos palpites que ele não pode validar. Nada é gravado.
 *
 * @property array{aposta: Apostas, palpites: list<array<string, mixed>>, calculo: array<string, string>} $resource
 */
class SimulacaoApostaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aposta = $this->resource['aposta'];
        $calculo = $this->resource['calculo'];
        $fuso = FusoSistema::do_pedido($request->input('fuso_horario'));

        return [
            'codigo' => $aposta->codigo,
            'nome' => $aposta->nome,
            'valor' => (string) $aposta->valor,
            'expira_em' => $aposta->expira_em?->copy()->setTimezone($fuso)->toIso8601String(),
            'cotacao_total' => $calculo['cotacao_total'],
            'premio' => $calculo['premio'],
            'valor_acrescido' => $calculo['valor_acrescido'],
            'total_a_pagar' => $calculo['total_a_pagar'],
            'palpites' => array_map(fn (array $palpite) => $palpite['codigo_cotacao'] === CodigosCotacao::ESPECIAL ? [
                // palpite especial: "Vencedor" e a categoria no lugar dos times (spec 006, FR-019)
                'indice' => $palpite['indice'],
                'confrontos_id' => null,
                'especiais_id' => $palpite['especiais_id'],
                'especiais_opcoes_id' => $palpite['especiais_opcoes_id'],
                'codigo_cotacao' => $palpite['codigo_cotacao'],
                'confrontos_jogadores_id' => null,
                'mercado' => $palpite['opcao_especial']?->nome,
                'jogador' => null,
                'confronto' => RegrasAposta::nome_confronto($palpite),
                'time_casa' => 'Vencedor',
                'time_fora' => $palpite['especial']?->nome,
                'data_inicio' => $palpite['especial']?->data_limite->copy()->setTimezone($fuso)->toIso8601String(),
                'esporte' => 'ESPECIAL',
                'cotacao' => $palpite['cotacao_atual'],
                'disponivel' => $palpite['motivo'] === null,
                'motivo' => $palpite['motivo'],
            ] : [
                'indice' => $palpite['indice'],
                'confrontos_id' => $palpite['confrontos_id'],
                'codigo_cotacao' => $palpite['codigo_cotacao'],
                'confrontos_jogadores_id' => $palpite['confrontos_jogadores_id'],
                'mercado' => NomesCotacoes::nome($palpite['codigo_cotacao'], $palpite['jogador_tipo']),
                'jogador' => $palpite['jogador']?->nome,
                'confronto' => $palpite['confronto'] !== null ? RegrasAposta::nome_confronto($palpite) : null,
                'time_casa' => $palpite['confronto']?->time_casa,
                'time_fora' => $palpite['confronto']?->time_fora,
                'data_inicio' => $palpite['confronto'] !== null
                    ? Carbon::parse($palpite['confronto']->data_inicio, 'UTC')->setTimezone($fuso)->toIso8601String()
                    : null,
                'cotacao' => $palpite['cotacao_atual'],
                'disponivel' => $palpite['motivo'] === null,
                'motivo' => $palpite['motivo'],
            ], $this->resource['palpites']),
        ];
    }
}
