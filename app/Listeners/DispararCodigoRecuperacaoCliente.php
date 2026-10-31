<?php

namespace App\Listeners;

use App\Events\CodigoRecuperacaoGerado;
use App\Services\DisparoMensagens;
use App\Services\TextosMensagens;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia o código de recuperação do cliente (spec 025, US1). Uma falha aqui nunca desfaz o pedido, e o
 * código nunca vai para o log.
 */
class DispararCodigoRecuperacaoCliente
{
    public function __construct(private DisparoMensagens $disparo, private TextosMensagens $textos) {}

    public function handle(CodigoRecuperacaoGerado $evento): void
    {
        try {
            $this->disparo->enviar($this->textos->codigo_recuperacao_cliente($evento->cliente, $evento->codigo, $evento->codigo_id, $evento->validade_minutos));
        } catch (Throwable $erro) {
            Log::error('Falha ao disparar o código de recuperação do cliente.', ['clientes_id' => $evento->cliente->id, 'erro' => $erro::class]);
        }
    }
}
