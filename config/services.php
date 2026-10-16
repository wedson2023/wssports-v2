<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // provedor de cotações (pré-jogo, ao vivo e conferência); a API exige a chave e o endereço do
    // site nos parâmetros "key" e "app" da URL, por isso a URL nunca vai para o log
    'provedor_cotacoes' => [
        // endereços base; as rotas (campeonatos, confrontos, cotacao, aovivo, confrontos/{id},
        // resultados e cancelados) ficam no código
        'url_pre_jogo' => env('PROVEDOR_COTACOES_URL_PRE_JOGO', 'https://apiprejogo.wssports.bet/api'),
        'url_ao_vivo' => env('PROVEDOR_COTACOES_URL_AO_VIVO', 'https://apiaovivo.wssports.bet/api'),
        'url_conferencia' => env('PROVEDOR_COTACOES_URL_CONFERENCIA', 'https://api.oddbrasil.com/bet/v2'),
        'chave' => env('PROVEDOR_COTACOES_CHAVE'),
        // endereço do site enviado no parâmetro "app"; vazio usa o app.url
        'app' => env('PROVEDOR_COTACOES_APP'),
        // tempos limite, em segundos: o ao vivo precisa caber no ciclo de 5 segundos
        'tempo_limite_campeonatos' => 60,
        'tempo_limite_confrontos' => 180,
        'tempo_limite_cotacoes' => 180,
        'tempo_limite_ao_vivo' => 4,
        'tempo_limite_conferencia' => 10,
        // apuração (spec 009)
        'tempo_limite_resultados' => 180,
        'tempo_limite_cancelados' => 60,
    ],

    // Mercado Pago: credenciais da aplicação do programador (conta master), a mesma em todas as
    // instalações; cada banca vincula a própria conta a ela pelo painel (OAuth) e a comissão vai
    // para a conta master
    'mercado_pago' => [
        'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
        'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
        // chave pública da aplicação: usada no navegador para capturar o cartão
        'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
        // assinatura secreta das notificações (webhooks) da aplicação
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        // chave privada (PEM) que assina os pedidos de saque automático (Payouts) em produção
        'payouts_chave_privada' => env('MERCADO_PAGO_PAYOUTS_CHAVE_PRIVADA'),
        'url_api' => 'https://api.mercadopago.com',
        'url_autorizacao' => 'https://auth.mercadopago.com.br/authorization',
    ],

    // notificações push do aplicativo (spec 010, R-13): par de chaves VAPID próprio, sem serviço
    // externo; gerar uma vez com "php artisan notificacoes_push:gerar_chaves". Trocar as chaves
    // invalida as inscrições dos aparelhos
    'notificacoes_push' => [
        'chave_publica' => env('VAPID_PUBLIC_KEY'),
        'chave_privada' => env('VAPID_PRIVATE_KEY'),
        // contato do remetente exigido pelos serviços de push (mailto: ou https:)
        'assunto' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'contato@example.com')),
    ],

    // provedor do cassino (spec 011, TBS2): hall e chave do hall só no .env; a chave vai no corpo das
    // chamadas e nunca é registrada no log nem no banco
    'provedor_cassino' => [
        // endereço base da API; a abertura do jogo usa o mesmo endereço + "openGame/"
        'url_api' => env('CASSINO_URL_API', 'http://tbs2api.aslot.net/API/'),
        'hall' => env('CASSINO_HALL'),
        'chave' => env('CASSINO_CHAVE'),
        // confere a assinatura "sign" (sha256) das chamadas do provedor; desligada até conferir a
        // ordem dos campos com uma chamada real
        'assinatura' => (bool) env('CASSINO_ASSINATURA', false),
        // prefixo do login do cliente no provedor (prefixo + id do cliente)
        'prefixo_login' => env('CASSINO_PREFIXO_LOGIN', 'wss'),
        // tempos limite, em segundos
        'tempo_limite_lista' => 60,
        'tempo_limite_abertura' => 10,
        'tempo_limite_historico' => 10,
    ],

];
