<?php

namespace App\Services;

use App\Enums\AceitarAlteracoes;
use App\Exceptions\RegraApostaException;

/**
 * Compara a cotação que o apostador viu com a atual (R-16, FR-027 a FR-029). Cotação indisponível
 * nunca vira 1,00: o palpite é devolvido para ser removido.
 */
class ConferenciaCotacoes
{
    /**
     * @param  list<array<string, mixed>>  $palpites  palpites montados (CriacaoApostas::montar)
     *
     * @throws RegraApostaException com a lista de palpites indisponíveis
     */
    public function garantir_disponiveis(array $palpites): void
    {
        $indisponiveis = [];

        foreach ($palpites as $palpite) {
            if (bccomp($palpite['cotacao_atual'], '0', 2) <= 0) {
                $indisponiveis[] = [
                    ...$this->identificacao($palpite),
                    'motivo' => match (true) {
                        $palpite['travado'] => 'Jogo travado no momento.',
                        ($palpite['especiais_opcoes_id'] ?? null) !== null => 'Opção especial indisponível.',
                        default => 'Cotação indisponível.',
                    },
                ];
            }
        }

        if ($indisponiveis !== []) {
            throw new RegraApostaException('Há palpites indisponíveis. Remova-os para continuar.', $indisponiveis);
        }
    }

    /**
     * Palpites cuja cotação atual difere da vista fora da preferência do apostador e, no Criar
     * Aposta (spec 008, R-09), blocos cuja cotação calculada agora difere da vista (muda também
     * quando o desconto muda).
     *
     * @param  list<array<string, mixed>>  $palpites
     * @param  list<array<string, mixed>>  $pernas  pernas da aposta (CalculoBlocos::pernas)
     * @param  list<array{confrontos_id: int, cotacao_vista: string}>  $blocos  blocos do pedido
     * @return list<array<string, mixed>>
     */
    public function alteracoes(array $palpites, AceitarAlteracoes $preferencia, array $pernas = [], array $blocos = []): array
    {
        $alteracoes = [];

        foreach ($palpites as $palpite) {
            if (! $preferencia->aceita($palpite['cotacao_vista'], $palpite['cotacao_atual'])) {
                $alteracoes[] = [
                    ...$this->identificacao($palpite),
                    'codigo_cotacao' => $palpite['codigo_cotacao'],
                    'cotacao_vista' => $palpite['cotacao_vista'],
                    'cotacao_atual' => $palpite['cotacao_atual'],
                ];
            }
        }

        $vistas = array_column($blocos, 'cotacao_vista', 'confrontos_id');

        foreach (CalculoBlocos::blocos($pernas) as $bloco) {
            $vista = bcadd($vistas[$bloco['confrontos_id']] ?? '0', '0', 2);

            if (! $preferencia->aceita($vista, $bloco['cotacao'])) {
                $alteracoes[] = [
                    'bloco' => true,
                    'confrontos_id' => $bloco['confrontos_id'],
                    'cotacao_vista' => $vista,
                    'cotacao_atual' => $bloco['cotacao'],
                ];
            }
        }

        return $alteracoes;
    }

    /**
     * @param  array<string, mixed>  $palpite
     * @return array<string, mixed>
     */
    private function identificacao(array $palpite): array
    {
        return match (true) {
            ($palpite['especiais_opcoes_id'] ?? null) !== null => ['indice' => $palpite['indice'], 'especiais_opcoes_id' => $palpite['especiais_opcoes_id']],
            $palpite['confrontos_ao_vivo_id'] !== null => ['indice' => $palpite['indice'], 'confrontos_ao_vivo_id' => $palpite['confrontos_ao_vivo_id']],
            default => ['indice' => $palpite['indice'], 'confrontos_id' => $palpite['confrontos_id']],
        };
    }
}
