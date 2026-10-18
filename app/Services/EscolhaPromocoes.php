<?php

namespace App\Services;

use App\Enums\CategoriaPromocao;
use App\Enums\TipoGanho;
use App\Models\Clientes;
use App\Models\ClientesPromocoes;

/**
 * Qual promoção de depósito vale para o cliente e quanto ela dá (spec 007, research R-11 e R-15).
 */
class EscolhaPromocoes
{
    /**
     * Promoção do afiliado do cliente antes da sem afiliado; "Qualquer depósito" antes de
     * "Primeiro depósito" (como no antigo); a mais antiga primeiro.
     */
    public function para_deposito(Clientes $cliente, string $valor, bool $primeiro_deposito): ?ClientesPromocoes
    {
        $categorias = [CategoriaPromocao::QualquerDepósito];

        if ($primeiro_deposito) {
            $categorias[] = CategoriaPromocao::PrimeiroDepósito;
        }

        return ClientesPromocoes::vigentes()
            ->whereIn('categoria', $categorias)
            ->paraAfiliado($cliente->afiliados_id)
            ->where(fn ($limite) => $limite->whereNull('valor_maximo_deposito')->orWhere('valor_maximo_deposito', '>=', $valor))
            ->orderByRaw('afiliados_id is null')
            ->orderByRaw('categoria = ?', [CategoriaPromocao::PrimeiroDepósito->value])
            ->orderBy('id')
            ->first();
    }

    /**
     * Valor do bônus: fixo, ou percentual do depósito arredondado em centavos (meio para cima).
     */
    public function valor_do_bonus(ClientesPromocoes $promocao, string $valor): string
    {
        if ($promocao->tipo_ganho === TipoGanho::Fixo) {
            return (string) $promocao->valor;
        }

        return self::percentual($valor, (string) $promocao->valor);
    }

    /**
     * percentual% de valor, arredondado em centavos (meio para cima), sem ponto flutuante.
     */
    public static function percentual(string $valor, string $percentual): string
    {
        // centavos × percentual com 2 casas → divide por 100 (percentual) e por 100 (casas)
        $centavos_vezes_10000 = SaldoClientes::para_centavos($valor) * SaldoClientes::para_centavos($percentual);
        $centavos = intdiv($centavos_vezes_10000 + 5000, 10000);

        return SaldoClientes::para_decimal($centavos);
    }
}
