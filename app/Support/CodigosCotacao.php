<?php

namespace App\Support;

/**
 * Códigos de cotação do provedor (odd1 a odd323) e o código "jogador", usado só nas regras
 * (porcentagens e teto).
 */
class CodigosCotacao
{
    public const JOGADOR = 'jogador';

    public const QUANTIDADE = 323;

    /**
     * @var list<string>|null
     */
    private static ?array $cotacoes = null;

    /**
     * Códigos de cotação: odd1 a odd323.
     *
     * @return list<string>
     */
    public static function cotacoes(): array
    {
        return self::$cotacoes ??= array_map(fn (int $numero) => "odd{$numero}", range(1, self::QUANTIDADE));
    }

    /**
     * Códigos aceitos nas regras: as 323 cotações e o de jogador.
     *
     * @return list<string>
     */
    public static function regras(): array
    {
        return [...self::cotacoes(), self::JOGADOR];
    }

    public static function e_cotacao(string $codigo): bool
    {
        return preg_match('/^odd([1-9]\d{0,2})$/', $codigo, $partes) === 1 && (int) $partes[1] <= self::QUANTIDADE;
    }

    public static function e_regra(string $codigo): bool
    {
        return $codigo === self::JOGADOR || self::e_cotacao($codigo);
    }
}
