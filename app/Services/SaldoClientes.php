<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\TipoTransacao;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\Clientes;
use App\Models\ClientesTransacoes;
use App\Models\Usuarios;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

/**
 * Única forma de alterar os saldos de um cliente. Cada movimentação gera exatamente uma
 * transação com o saldo anterior e o posterior da carteira afetada.
 */
class SaldoClientes
{
    public function creditar(
        Clientes $cliente,
        Carteira $carteira,
        string $valor,
        OrigemTransacao $origem,
        ?Usuarios $autor = null,
        ?int $referencia_id = null,
        ?string $observacao = null,
    ): ClientesTransacoes {
        return $this->movimentar($cliente, $carteira, TipoTransacao::Crédito, $valor, $origem, $autor, $referencia_id, $observacao);
    }

    /**
     * @throws SaldoInsuficienteException quando o valor é maior que o saldo da carteira
     */
    public function debitar(
        Clientes $cliente,
        Carteira $carteira,
        string $valor,
        OrigemTransacao $origem,
        ?Usuarios $autor = null,
        ?int $referencia_id = null,
        ?string $observacao = null,
    ): ClientesTransacoes {
        return $this->movimentar($cliente, $carteira, TipoTransacao::Débito, $valor, $origem, $autor, $referencia_id, $observacao);
    }

    private function movimentar(
        Clientes $cliente,
        Carteira $carteira,
        TipoTransacao $tipo,
        string $valor,
        OrigemTransacao $origem,
        ?Usuarios $autor,
        ?int $referencia_id,
        ?string $observacao,
    ): ClientesTransacoes {
        $valor_centavos = self::para_centavos($valor);

        if ($valor_centavos <= 0) {
            throw new InvalidArgumentException('O valor da movimentação deve ser maior que zero.');
        }

        return DB::transaction(function () use ($cliente, $carteira, $tipo, $valor_centavos, $origem, $autor, $referencia_id, $observacao) {
            // o bloqueio da linha do cliente serializa movimentações simultâneas no mesmo cliente,
            // garantindo que o saldo anterior de uma seja o posterior da outra
            $cliente_bloqueado = Clientes::withTrashed()->lockForUpdate()->findOrFail($cliente->id);
            $coluna = $carteira->coluna();

            $anterior = self::para_centavos((string) $cliente_bloqueado->getRawOriginal($coluna));

            if ($tipo === TipoTransacao::Débito && $valor_centavos > $anterior) {
                throw new SaldoInsuficienteException;
            }

            $posterior = $tipo === TipoTransacao::Crédito ? $anterior + $valor_centavos : $anterior - $valor_centavos;

            $cliente_bloqueado->forceFill([$coluna => self::para_decimal($posterior)])->save();
            $cliente->setRawAttributes($cliente_bloqueado->getAttributes(), true);

            return ClientesTransacoes::create([
                'clientes_id' => $cliente_bloqueado->id,
                'carteira' => $carteira,
                'tipo' => $tipo,
                'origem' => $origem,
                'referencia_id' => $referencia_id,
                'valor' => self::para_decimal($valor_centavos),
                'saldo_anterior' => self::para_decimal($anterior),
                'saldo_posterior' => self::para_decimal($posterior),
                'usuarios_id' => $autor?->id,
                'observacao' => $observacao,
            ]);
        });
    }

    /**
     * Converte um decimal em texto ("12.5", "12.50") para centavos inteiros, sem usar float.
     */
    public static function para_centavos(string $valor): int
    {
        $negativo = str_starts_with($valor, '-');
        [$inteiro, $fracao] = array_pad(explode('.', ltrim($valor, '-')), 2, '');
        $centavos = (int) $inteiro * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');

        return $negativo ? -$centavos : $centavos;
    }

    public static function para_decimal(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $sinal.intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
