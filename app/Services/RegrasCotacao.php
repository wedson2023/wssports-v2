<?php

namespace App\Services;

use App\Models\Confrontos;
use App\Support\CodigosCotacao;
use Illuminate\Validation\ValidationException;

/**
 * Alteração dos valores das regras de cotação (porcentagens e teto) e cálculo do valor fixo de um
 * confronto a partir da cotação desejada.
 */
class RegrasCotacao
{
    /**
     * "valores" mescla com os atuais (0 remove o código); "todos" substitui os 324 códigos (0 remove
     * todos).
     *
     * @param  array<string, mixed>  $atuais
     * @param  array<string, mixed>|null  $valores
     * @return array<string, float>
     */
    public function aplicar(array $atuais, ?array $valores, int|float|string|null $todos): array
    {
        if ($todos !== null) {
            $todos = round((float) $todos, 2);

            return $todos == 0 ? [] : array_fill_keys(CodigosCotacao::regras(), $todos);
        }

        foreach ($valores ?? [] as $codigo => $valor) {
            $valor = round((float) $valor, 2);

            if ($valor == 0) {
                unset($atuais[$codigo]);
            } else {
                $atuais[$codigo] = $valor;
            }
        }

        return $atuais;
    }

    /**
     * Valor fixo por código = cotação desejada − cotação atual do provedor. Código com cotação
     * zerada no provedor não pode ser alterado.
     *
     * @param  array<string, mixed>  $desejadas
     * @return array<string, float>
     */
    public function diferencas_confronto(Confrontos $confronto, array $desejadas): array
    {
        $diferencas = [];

        foreach ($desejadas as $codigo => $desejada) {
            $base = $confronto->cotacao($codigo);

            if ($base <= 0) {
                throw ValidationException::withMessages([
                    "cotacoes.{$codigo}" => "A cotação {$codigo} está indisponível no provedor.",
                ]);
            }

            $diferencas[$codigo] = round((float) $desejada - $base, 2);
        }

        return $diferencas;
    }
}
