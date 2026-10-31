# Quickstart: validação manual

## Pré-requisitos

- `.env` com `MAIL_*` e `EVOLUTION_*` preenchidos, e robô conectado em Configurações → Integrações.
- Migrations aplicadas e agendador rodando.
- Worker atendendo as filas, com a prioritária primeiro:

```bash
php artisan queue:work --queue=apostas,mensagens_prioritarias,notificacoes,mensagens,default
```

- No servidor, incluir `mensagens_prioritarias` e `mensagens` no programa `wssports-fila` do Supervisor. Sem isso as mensagens ficam paradas na fila.

## §1 Confirmar os canais (fazer primeiro)

1. Em Configurações → Integrações → Mensagens, preencher telefone e e-mail do Admin e salvar.
2. Clicar em "Testar WhatsApp". Esperado: mensagem recebida. Se falhar com 404, o caminho de R-01 está errado e deve ser corrigido antes de seguir.
3. Clicar em "Testar e-mail". Esperado: e-mail recebido, o que confirma o login do SMTP. Conferir também a caixa de spam.
4. Enviar para um número sem WhatsApp e anotar em research.md (R-02) a resposta real da Evolution.

## §2 Recuperação de senha (US1)

1. Pedir recuperação com o telefone de um cliente. Esperado: código no WhatsApp em até 1 minuto.
2. Redefinir a senha com o código.
3. Buscar o código em `storage/logs`, `mensagens_envios`, `jobs` e `failed_jobs`. Esperado: nenhum resultado em texto puro.
4. Desconectar o robô e pedir de novo para um cliente com e-mail. Esperado: código no e-mail.
5. Pedir com telefone sem cadastro. Esperado: mesma resposta e nada enviado.
6. Pedir duas vezes com a fila parada e ligar o worker. Esperado: só o último código sai.

## §3 Boas-vindas e canais (US2, US6)

1. Cadastrar cliente com e-mail. Esperado: WhatsApp e e-mail.
2. Mudar para "E-mail" e cadastrar outro. Esperado: só o e-mail.
3. Com o SMTP errado, cadastrar. Esperado: cadastro concluído e falha em `mensagens_envios`.
4. Entrar como Supervisor. Esperado: card não aparece; a rota responde 403.

## §4 Avisos ao Admin (US3)

1. Gerar uma combinação de risco. Esperado: mesma mensagem de antes, sem CallMeBot.
2. Criar um saque manual e ajustar `created_at` para 31 minutos atrás. Esperado: um único aviso com cliente, valor, chave Pix e link que pede login.
3. Decidir um saque antes dos 30 minutos. Esperado: sem aviso.
4. Desconectar o robô e repetir. Esperado: aviso no e-mail.
5. Limpar telefone e e-mail do Admin. Esperado: registro `Dispensado`.

## §5 Queda do robô (US4)

1. Desconectar o aparelho pelo celular. Esperado: e-mail em até 5 minutos e faixa no painel para o Admin.
2. Reconectar. Esperado: faixa some.
3. Desconectar pelo painel. Esperado: sem e-mail e sem faixa.
4. Derrubar duas vezes em 30 minutos. Esperado: um só e-mail.

## §6 Modelos de e-mail (US7)

Disparar cada tipo por e-mail e abrir no celular e no computador. Esperado: logo e cor da banca, assunto claro, texto curto, no máximo um botão e código visível sem rolar.

## §7 Deploy

1. Rodar um deploy. Esperado: aviso no WhatsApp pelo CallMeBot, como hoje.
2. Buscar "callmebot" em `app` e `resources`. Esperado: nenhum resultado.
