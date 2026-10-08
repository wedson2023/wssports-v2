<?php

namespace App\Services;

use App\Models\Apostas;
use App\Models\ApostasPalpites;

/**
 * Assinatura HMAC-SHA256 da aposta (R-11): detecta alteração posterior de valor, prêmio ou
 * cotações. A chave é derivada da chave da aplicação.
 */
class AssinaturaApostas
{
    public function assinar(Apostas $aposta): string
    {
        return hash_hmac('sha256', $this->canonico($aposta), $this->chave());
    }

    public function conferir(Apostas $aposta): bool
    {
        return $aposta->assinatura !== null && hash_equals($aposta->assinatura, $this->assinar($aposta));
    }

    /**
     * JSON com chaves ordenadas dos campos protegidos.
     */
    private function canonico(Apostas $aposta): string
    {
        $palpites = $aposta->palpites_ativos()->orderBy('id')->get()
            ->map(fn (ApostasPalpites $palpite) => [
                'codigo_cotacao' => $palpite->codigo_cotacao,
                'confrontos_id' => $palpite->confrontos_id,
                'confrontos_jogadores_id' => $palpite->confrontos_jogadores_id,
                'cotacao_final' => (string) $palpite->cotacao_final,
                'jogador_tipo' => $palpite->jogador_tipo,
            ])
            ->all();

        $dados = [
            'codigo' => $aposta->codigo,
            'confirmada_em' => $aposta->confirmada_em?->utc()->toIso8601String(),
            'cotacao_total' => (string) $aposta->cotacao_total,
            'palpites' => $palpites,
            'premio' => (string) $aposta->premio,
            'valor' => (string) $aposta->valor,
            'valor_acrescido' => (string) $aposta->valor_acrescido,
        ];

        return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function chave(): string
    {
        return hash_hmac('sha256', 'apostas', (string) config('app.key'));
    }
}
