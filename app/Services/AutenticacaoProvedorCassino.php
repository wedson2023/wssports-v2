<?php

namespace App\Services;

/**
 * Confere se a chamada veio do provedor do cassino (spec 011, R-13): hall e chave do hall em toda
 * chamada e, com a assinatura ligada, o "sign" (sha256).
 */
class AutenticacaoProvedorCassino
{
    /**
     * @param  array<string, mixed>  $dados
     * @return string|null null quando a chamada é válida; senão, o código de erro do provedor
     */
    public function validar(array $dados): ?string
    {
        $config = config('services.provedor_cassino');

        if (! hash_equals((string) $config['hall'], (string) ($dados['hall'] ?? ''))) {
            return 'wrong_hall';
        }

        if (! hash_equals((string) $config['chave'], (string) ($dados['key'] ?? ''))) {
            return 'wrong_key';
        }

        if ($config['assinatura'] && ! hash_equals($this->assinatura($dados), (string) ($dados['sign'] ?? ''))) {
            return 'wrong_sign';
        }

        return null;
    }

    /**
     * sha256 dos campos da chamada, sem o próprio "sign", em ordem alfabética das chaves, unidos por
     * ":" e seguidos da chave do hall. Conferir a ordem com o PDF/chamada real antes de ligar a
     * assinatura (CASSINO_ASSINATURA).
     *
     * @param  array<string, mixed>  $dados
     */
    private function assinatura(array $dados): string
    {
        unset($dados['sign']);
        ksort($dados);

        return hash('sha256', implode(':', array_map(fn ($valor) => is_scalar($valor) ? (string) $valor : json_encode($valor), $dados)).config('services.provedor_cassino.chave'));
    }
}
