<?php

namespace App\Console\Commands;

use App\Services\VinculoMercadoPago;
use Illuminate\Console\Command;

/**
 * Renova o token da conta do Mercado Pago perto de vencer (spec 007, research R-05).
 */
class RenovarTokenMercadoPagoCommand extends Command
{
    protected $signature = 'mercado_pago:renovar_token';

    protected $description = 'Renova o token da conta do Mercado Pago vinculada quando faltam menos de 30 dias para vencer';

    public function handle(VinculoMercadoPago $vinculo): int
    {
        $resultado = $vinculo->renovar();

        $this->info(match ($resultado) {
            'renovado' => 'Token do Mercado Pago renovado.',
            'falhou' => 'A renovação falhou; o motivo ficou registrado no vínculo.',
            default => 'Nada a renovar.',
        });

        return self::SUCCESS;
    }
}
