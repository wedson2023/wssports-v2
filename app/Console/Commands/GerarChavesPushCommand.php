<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

/**
 * Gera o par de chaves VAPID das notificações push (spec 010, R-13). Só mostra as chaves: quem
 * instala copia para o .env. Não grava nada.
 */
class GerarChavesPushCommand extends Command
{
    protected $signature = 'notificacoes_push:gerar_chaves';

    protected $description = 'Gera o par de chaves VAPID das notificações push para copiar no .env';

    public function handle(): int
    {
        try {
            $chaves = VAPID::createVapidKeys();
        } catch (Throwable $erro) {
            $this->error('Não foi possível gerar as chaves: '.$erro->getMessage());
            $this->line('No Windows, defina a variável OPENSSL_CONF com o caminho do openssl.cnf do PHP (ex.: C:\xampp\php\extras\ssl\openssl.cnf) e rode de novo.');

            return self::FAILURE;
        }

        $this->info('Copie as linhas abaixo para o .env:');
        $this->line('VAPID_PUBLIC_KEY='.$chaves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$chaves['privateKey']);
        $this->warn('Trocar as chaves invalida as inscrições dos aparelhos: os clientes precisam ativar as notificações de novo.');

        return self::SUCCESS;
    }
}
