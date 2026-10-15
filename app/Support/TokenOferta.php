<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Token da oferta de encerramento (spec 010, R-09): os dados exibidos ao cliente assinados com
 * HMAC-SHA256 e a chave da aplicação. Prova o que foi oferecido e impede alterar o valor no envio.
 */
final class TokenOferta
{
    /**
     * @param  array<string, mixed>  $dados  precisa de expira_em (ISO 8601)
     */
    public static function assinar(array $dados): string
    {
        $corpo = self::base64url(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $corpo.'.'.self::base64url(hash_hmac('sha256', $corpo, self::chave(), true));
    }

    /**
     * Dados do token, ou nulo quando adulterado, malformado ou vencido.
     *
     * @return array<string, mixed>|null
     */
    public static function conferir(string $token): ?array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 2) {
            return null;
        }

        [$corpo, $assinatura] = $partes;
        $esperada = self::base64url(hash_hmac('sha256', $corpo, self::chave(), true));

        if (! hash_equals($esperada, $assinatura)) {
            return null;
        }

        $dados = json_decode(self::de_base64url($corpo), true);

        if (! is_array($dados) || ! isset($dados['expira_em']) || Carbon::parse($dados['expira_em'])->isPast()) {
            return null;
        }

        return $dados;
    }

    private static function chave(): string
    {
        return (string) config('app.key');
    }

    private static function base64url(string $texto): string
    {
        return rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
    }

    private static function de_base64url(string $texto): string
    {
        return (string) base64_decode(strtr($texto, '-_', '+/'));
    }
}
