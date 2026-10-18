<?php

namespace App\Listeners;

use App\Events\ClienteCadastrado;
use App\Mail\ClienteCadastroRealizado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mail de boas-vindas do cliente que informou e-mail no cadastro (spec 012, FR-013a). Uma falha
 * aqui nunca desfaz o cadastro nem impede a entrada do cliente.
 */
class EnviarEmailCadastroCliente
{
    public function handle(ClienteCadastrado $evento): void
    {
        if ($evento->cliente->email === null) {
            return;
        }

        try {
            Mail::to($evento->cliente->email)->queue(new ClienteCadastroRealizado($evento->cliente));
        } catch (Throwable $erro) {
            Log::error('Falha ao enviar o e-mail de cadastro do cliente.', ['clientes_id' => $evento->cliente->id, 'erro' => $erro->getMessage()]);
        }
    }
}
