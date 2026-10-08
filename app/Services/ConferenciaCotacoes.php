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
                    'motivo' => $palpite['travado'] ? 'Jogo travado no momento.' : 'Cotação indisponível.',
                ];
            }
        }

        if ($indisponiveis !== []) {
            throw new RegraApostaException('Há palpites indisponíveis. Remova-os para continuar.', $indisponiveis);
        }
    }

    /**
     * Palpites cuja cotação atual difere da vista fora da preferência do apostador.
     *
     * @param  list<array<string, mixed>>  $palpites
     * @return list<array<string, mixed>>
     */
    public function alteracoes(array $palpites, AceitarAlteracoes $preferencia): array
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

        return $alteracoes;
    }

    /**
     * @param  array<string, mixed>  $palpite
     * @return array<string, mixed>
     */
    private function identificacao(array $palpite): array
    {
        return $palpite['confrontos_ao_vivo_id'] !== null
            ? ['indice' => $palpite['indice'], 'confrontos_ao_vivo_id' => $palpite['confrontos_ao_vivo_id']]
            : ['indice' => $palpite['indice'], 'confrontos_id' => $palpite['confrontos_id']];
    }
}
