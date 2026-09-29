<?php

namespace App\Listeners;

use App\Events\ClienteCadastrado;
use App\Events\CodigoRecuperacaoGerado;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enquanto a spec de WhatsApp não existir, as mensagens são só registradas no log.
 * Uma falha aqui nunca pode desfazer o cadastro nem a recuperação de senha.
 */
class RegistrarMensagemWhatsapp
{
    public function handle(ClienteCadastrado|CodigoRecuperacaoGerado $evento): void
    {
        try {
            $mensagem = $evento instanceof CodigoRecuperacaoGerado
                ? "Seu código de recuperação é {$evento->codigo}, válido por 15 minutos."
                : "Olá, {$evento->cliente->nome}! Sua conta foi criada com sucesso.";

            Log::info('WhatsApp (envio pendente da spec de WhatsApp)', [
                'ddi' => $evento->cliente->ddi,
                'telefone' => $evento->cliente->telefone,
                'mensagem' => $mensagem,
            ]);
        } catch (Throwable $erro) {
            Log::error('Falha ao registrar a mensagem de WhatsApp.', ['erro' => $erro->getMessage()]);
        }
    }
}
