<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ com 14 dígitos, não repetidos, e dígitos verificadores corretos.
 * Espera o valor já normalizado para só dígitos.
 */
class CnpjValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('O CNPJ informado é inválido.');
        }
    }

    public static function valido(string $cnpj): bool
    {
        if (! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([12, 13] as $posicao) {
            $soma = 0;
            $pesos_usados = array_slice($pesos, 13 - $posicao);

            for ($indice = 0; $indice < $posicao; $indice++) {
                $soma += (int) $cnpj[$indice] * $pesos_usados[$indice];
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
