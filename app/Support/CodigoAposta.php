<?php

namespace App\Support;

/**
 * Código de 8 caracteres de uma aposta, sem caracteres ambíguos (0, O, 1, I).
 */
class CodigoAposta
{
    public const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const TAMANHO = 8;

    /**
     * Sorteia um código com gerador criptograficamente seguro.
     */
    public static function gerar(): string
    {
        $ultimo = strlen(self::ALFABETO) - 1;
        $codigo = '';

        for ($i = 0; $i < self::TAMANHO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $ultimo)];
        }

        return $codigo;
    }

    /**
     * Código como o apostador digitou, em maiúsculas e sem espaços.
     */
    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $codigo));
    }
}
