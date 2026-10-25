<?php

namespace App\Services;

use App\Enums\OrigemTransacao;
use App\Enums\SituacaoBonus;
use App\Enums\TipoEstornoBonus;
use App\Models\Clientes;
use App\Models\ClientesPromocoesEstornos;
use App\Models\ClientesRollovers;
use App\Models\ClientesTransacoes;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Estorno do bônus de um cliente (spec 020, FR-027 e FR-028, research R-05): retira do saldo
 * promocional o menor entre o saldo e o valor do bônus, sem negativar, faz o rollover deixar de valer
 * e registra o estorno, um por crédito. O estorno geral é este mesmo estorno feito em cada bônus da
 * promoção. Nenhuma aposta é alterada.
 */
class EstornoBonus
{
    public function __construct(private SaldoClientes $saldo) {}

    /**
     * Estorna o bônus do crédito. No individual, bônus que não está em andamento nem cumprido é
     * recusado; no geral, é pulado (devolve null), assim como o já estornado.
     *
     * @throws ValidationException
     */
    public function estornar(ClientesTransacoes $credito, TipoEstornoBonus $tipo, ?Usuarios $autor, string $motivo): ?ClientesPromocoesEstornos
    {
        return DB::transaction(function () use ($credito, $tipo, $autor, $motivo) {
            // mesma ordem de trava do GestaoBonus (cliente → rollover), para serializar com converter,
            // excluir e expirar sem impasse
            $cliente = Clientes::withTrashed()->lockForUpdate()->findOrFail($credito->clientes_id);
            $rollover = ClientesRollovers::where('clientes_transacoes_id', $credito->id)->lockForUpdate()->first();
            $estornado = ClientesPromocoesEstornos::where('clientes_transacoes_id', $credito->id)->exists();

            if (! self::situacao($rollover, $estornado)->estornavel()) {
                if ($tipo === TipoEstornoBonus::Individual) {
                    throw ValidationException::withMessages(['bonus' => 'Este bônus não pode mais ser estornado.']);
                }

                return null;
            }

            $saldo_carteira = (string) $cliente->getRawOriginal($credito->carteira->coluna());
            $retirar = bccomp($saldo_carteira, (string) $credito->valor, 2) < 0 ? $saldo_carteira : (string) $credito->valor;
            $debito = null;

            if (bccomp($retirar, '0', 2) > 0) {
                $debito = $this->saldo->debitar($cliente, $credito->carteira, $retirar, OrigemTransacao::Estorno, $autor, $credito->referencia_id, "Estorno do bônus: {$motivo}");
            }

            if ($rollover !== null && $rollover->cancelado_em === null) {
                $rollover->forceFill(['cancelado_em' => now()])->save();
            }

            return ClientesPromocoesEstornos::create([
                'clientes_promocoes_id' => $credito->referencia_id,
                'clientes_id' => $credito->clientes_id,
                'clientes_transacoes_id' => $credito->id,
                'clientes_rollovers_id' => $rollover?->id,
                'tipo' => $tipo,
                'motivo' => $motivo,
                'usuarios_id' => $autor?->id,
                'valor_retirado' => $debito === null ? '0.00' : (string) $debito->valor,
                'debito_clientes_transacoes_id' => $debito?->id,
            ]);
        });
    }

    /**
     * Registra o cancelamento do bônus feito pelo sistema (estorno do depósito ou da indicação), que já
     * debitou por conta própria; não faz nada se o bônus já tem estorno.
     */
    public function registrar_automatico(int $clientes_transacoes_id, ?ClientesRollovers $rollover, string $motivo, string $valor_retirado, ?ClientesTransacoes $debito): void
    {
        $credito = ClientesTransacoes::findOrFail($clientes_transacoes_id);

        ClientesPromocoesEstornos::insertOrIgnore([
            'clientes_promocoes_id' => $credito->referencia_id,
            'clientes_id' => $credito->clientes_id,
            'clientes_transacoes_id' => $credito->id,
            'clientes_rollovers_id' => $rollover?->id,
            'tipo' => TipoEstornoBonus::Automático->value,
            'motivo' => $motivo,
            'usuarios_id' => null,
            'valor_retirado' => $valor_retirado,
            'debito_clientes_transacoes_id' => $debito?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Situação do bônus pela regra de R-03 (a mesma do CASE da listagem): o estorno vem antes da
     * exclusão, porque o cancelamento pelo estorno do depósito também marca excluido_em.
     */
    public static function situacao(?ClientesRollovers $rollover, bool $estornado): SituacaoBonus
    {
        return match (true) {
            $estornado => SituacaoBonus::Estornado,
            $rollover === null => SituacaoBonus::Cumprido,
            $rollover->convertido_em !== null => SituacaoBonus::Convertido,
            $rollover->expirado_em !== null => SituacaoBonus::Expirado,
            $rollover->excluido_em !== null => SituacaoBonus::ExcluídoPeloCliente,
            $rollover->cancelado_em !== null => SituacaoBonus::Estornado,
            $rollover->cumprido_em !== null => SituacaoBonus::Cumprido,
            default => SituacaoBonus::EmAndamento,
        };
    }
}
