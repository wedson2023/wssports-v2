<?php

namespace App\Listeners;

use App\Events\ClienteCadastrado;
use App\Services\ConfirmacaoCadastro;
use App\Services\DisparoMensagens;
use App\Services\TextosMensagens;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Boas-vindas do cliente (spec 025, US2), ou a confirmação de e-mail no lugar delas quando a banca a
 * exige (FR-030). Uma falha aqui nunca desfaz o cadastro.
 */
class DispararBoasVindasCliente
{
    public function __construct(
        private DisparoMensagens $disparo,
        private TextosMensagens $textos,
        private ConfirmacaoCadastro $confirmacao,
    ) {}

    public function handle(ClienteCadastrado $evento): void
    {
        try {
            if ($evento->cliente->confirmacao_pendente) {
                $this->confirmacao->enviar_link($evento->cliente);

                return;
            }

            $this->disparo->enviar($this->textos->boas_vindas_cliente($evento->cliente));
        } catch (Throwable $erro) {
            Log::error('Falha ao disparar as boas-vindas do cliente.', ['clientes_id' => $evento->cliente->id, 'erro' => $erro::class]);
        }
    }
}
