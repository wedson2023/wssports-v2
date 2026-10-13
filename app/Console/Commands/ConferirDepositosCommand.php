<?php

namespace App\Console\Commands;

use App\Enums\SituacaoDeposito;
use App\Exceptions\GatewayIndisponivelException;
use App\Models\Depositos;
use App\Services\ClienteMercadoPago;
use App\Services\ProcessamentoDepositos;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Conferência dos depósitos em aberto (spec 007, research R-10): expira os Pix vencidos e consulta
 * no Mercado Pago os pendentes das últimas 2 horas; cobre aviso perdido e ambiente sem webhook.
 */
class ConferirDepositosCommand extends Command
{
    protected $signature = 'depositos:conferir';

    protected $description = 'Expira os Pix vencidos e confere no Mercado Pago os depósitos pendentes';

    public function handle(ClienteMercadoPago $mercado_pago, ProcessamentoDepositos $processamento): int
    {
        $expirados = $processamento->expirar_vencidos();
        $conferidos = 0;

        $pendentes = Depositos::whereIn('situacao', [SituacaoDeposito::Pendente, SituacaoDeposito::EmAnálise])
            ->whereNotNull('pagamento_id')
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        foreach ($pendentes as $deposito) {
            try {
                $processamento->atualizar($mercado_pago->consultar_pagamento($deposito->pagamento_id));
                $conferidos++;
            } catch (GatewayIndisponivelException) {
                $this->warn('Conta do Mercado Pago não vinculada; conferência interrompida.');
                break;
            } catch (RequestException) {
                // a próxima execução tenta de novo
            }
        }

        $this->info("{$expirados} Pix expirado(s); {$conferidos} depósito(s) conferido(s).");

        return self::SUCCESS;
    }
}
