<?php

namespace App\Services;

use App\Enums\Carteira;
use App\Enums\OrigemTransacao;
use App\Enums\TipoRollover;
use App\Models\Clientes;
use App\Models\ClientesRollovers;
use App\Models\Usuarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converter, excluir e expirar bônus (spec 007, FR-015 a FR-018d). O saldo de bônus é guardado
 * por carteira, não por bônus (research R-22): o bônus cumprido libera a carteira inteira quando
 * não há outro bônus pendente na mesma modalidade; excluir e expirar tiram o menor entre o saldo
 * da carteira e o valor do bônus.
 */
class GestaoBonus
{
    public function __construct(private SaldoClientes $saldo) {}

    /**
     * Valor liberado para conversão deste bônus.
     */
    public function liberado(ClientesRollovers $bonus): string
    {
        if (! $this->ativo($bonus) || $bonus->cumprido_em === null || $this->outro_pendente($bonus)) {
            return '0.00';
        }

        $saldo = (string) $this->cliente($bonus)->{$bonus->carteira->coluna()};

        return bccomp($saldo, '0', 2) > 0 ? $saldo : '0.00';
    }

    /**
     * liberado, pode_converter e pode_excluir de cada linha de bônus do extrato.
     *
     * @return array{liberado: string, pode_converter: bool, pode_excluir: bool}
     */
    public function acoes(ClientesRollovers $bonus): array
    {
        $liberado = $this->liberado($bonus);

        return [
            'liberado' => $liberado,
            'pode_converter' => bccomp($liberado, '0', 2) > 0,
            'pode_excluir' => $this->ativo($bonus) && $bonus->cumprido_em === null,
        ];
    }

    /**
     * Passa até o valor máximo de conversão da promoção para o saldo e zera a carteira do bônus,
     * como o antigo (que tirava todo o valor liberado).
     *
     * @return string valor creditado no saldo
     */
    public function converter(Clientes $cliente, ClientesRollovers $bonus, ?Usuarios $autor = null): string
    {
        return DB::transaction(function () use ($cliente, $bonus, $autor) {
            $bonus = $this->bloquear($cliente, $bonus);

            if ($bonus->expirado_em !== null) {
                $this->recusar('Seu bônus está expirado.');
            }

            if ($bonus->convertido_em !== null || $bonus->excluido_em !== null || $bonus->cancelado_em !== null) {
                $this->recusar('Este bônus já cumpriu a meta ou o rollover foi removido.');
            }

            $liberado = $this->liberado($bonus);

            if (bccomp($liberado, '0', 2) <= 0) {
                $this->recusar($bonus->cumprido_em !== null && $this->outro_pendente($bonus)
                    ? 'Cumpra o rollover dos outros bônus desta modalidade antes de converter.'
                    : 'Você não tem valor liberado para conversão.');
            }

            $maximo = (string) ($bonus->promocao?->valor_maximo_conversao ?? $liberado);
            $valor = bccomp($liberado, $maximo, 2) > 0 ? $maximo : $liberado;

            $this->saldo->debitar($cliente, $bonus->carteira, $liberado, OrigemTransacao::ConversãoBônus, $autor, $bonus->clientes_promocoes_id, 'Bônus convertido para o saldo');
            $this->saldo->creditar($cliente, Carteira::Saldo, $valor, OrigemTransacao::ConversãoBônus, $autor, $bonus->clientes_promocoes_id, 'Bônus convertido para o saldo');
            $bonus->forceFill(['convertido_em' => now()])->save();

            return $valor;
        });
    }

    public function excluir(Clientes $cliente, ClientesRollovers $bonus, ?Usuarios $autor = null): void
    {
        DB::transaction(function () use ($cliente, $bonus, $autor) {
            $bonus = $this->bloquear($cliente, $bonus);

            if ($bonus->expirado_em !== null) {
                $this->recusar('Seu bônus está expirado.');
            }

            if (bccomp($this->liberado($bonus), '0', 2) > 0) {
                $this->recusar('Antes de excluir o bônus, converta o valor liberado.');
            }

            if (! $this->ativo($bonus) || $bonus->cumprido_em !== null) {
                $this->recusar('Este bônus já cumpriu a meta ou o rollover foi removido.');
            }

            $this->retirar($cliente, $bonus, OrigemTransacao::BônusExcluído, 'Bônus excluído pelo cliente', $autor);
            $bonus->forceFill(['excluido_em' => now(), 'cancelado_em' => now()])->save();
        });
    }

    /**
     * Retira os bônus vencidos que não cumpriram o rollover (FR-018b).
     */
    public function expirar_vencidos(): int
    {
        $vencidos = ClientesRollovers::where('tipo', TipoRollover::Bônus)
            ->pendentes()
            ->whereNotNull('expira_em')
            ->where('expira_em', '<', now())
            ->get();

        foreach ($vencidos as $bonus) {
            DB::transaction(function () use ($bonus) {
                $cliente = Clientes::withTrashed()->lockForUpdate()->findOrFail($bonus->clientes_id);
                $bonus = ClientesRollovers::lockForUpdate()->findOrFail($bonus->id);

                if (! $this->ativo($bonus) || $bonus->cumprido_em !== null) {
                    return;
                }

                $this->retirar($cliente, $bonus, OrigemTransacao::BônusExpirado, 'Prazo do bônus encerrado');
                $bonus->forceFill(['expirado_em' => now()])->save();
            });
        }

        return $vencidos->count();
    }

    /**
     * Tira da carteira o menor entre o saldo dela e o valor do bônus.
     */
    private function retirar(Clientes $cliente, ClientesRollovers $bonus, OrigemTransacao $origem, string $observacao, ?Usuarios $autor = null): void
    {
        $saldo = (string) $cliente->refresh()->{$bonus->carteira->coluna()};
        $retirar = bccomp($saldo, (string) $bonus->valor_creditado, 2) < 0 ? $saldo : (string) $bonus->valor_creditado;

        if (bccomp($retirar, '0', 2) > 0) {
            $this->saldo->debitar($cliente, $bonus->carteira, $retirar, $origem, $autor, $bonus->clientes_promocoes_id, $observacao);
        }
    }

    private function ativo(ClientesRollovers $bonus): bool
    {
        return $bonus->cancelado_em === null && $bonus->expirado_em === null && $bonus->excluido_em === null && $bonus->convertido_em === null;
    }

    private function outro_pendente(ClientesRollovers $bonus): bool
    {
        return ClientesRollovers::where('clientes_id', $bonus->clientes_id)
            ->where('tipo', TipoRollover::Bônus)
            ->where('carteira', $bonus->carteira)
            ->where('id', '!=', $bonus->id)
            ->pendentes()
            ->exists();
    }

    private function cliente(ClientesRollovers $bonus): Clientes
    {
        return Clientes::withTrashed()->findOrFail($bonus->clientes_id);
    }

    private function bloquear(Clientes $cliente, ClientesRollovers $bonus): ClientesRollovers
    {
        Clientes::withTrashed()->lockForUpdate()->findOrFail($cliente->id);

        return ClientesRollovers::with('promocao')->lockForUpdate()->findOrFail($bonus->id);
    }

    private function recusar(string $mensagem): never
    {
        throw ValidationException::withMessages(['bonus' => $mensagem]);
    }
}
