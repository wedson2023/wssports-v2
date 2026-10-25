# Quickstart — validação manual da spec 021

Sem testes automatizados (constituição). Siga os passos com o site rodando (`php artisan serve`,
`npm run dev`, fila e Reverb como nas specs anteriores).

## Pré-requisitos

1. `composer install` (traz `ezyang/htmlpurifier`) e `npm install` (traz o TipTap).
2. `php artisan migrate` (colunas novas, histórico, regras em HTML e permissão do robô).
3. `.env`: `EVOLUTION_API_URL`, `EVOLUTION_API_CHAVE`, `EVOLUTION_WEBHOOK_TOKEN` (e, se quiser,
   `EVOLUTION_API_INSTANCIA`); a Evolution precisa alcançar o `APP_URL` (ngrok em ambiente local).
4. Usuários de teste: um Admin, um Supervisor e um Gerente.

## 1. Acesso (FR-001)

- Admin e Supervisor veem "Configurações" no menu; o Gerente não, e `/api/configuracoes/banca`
  responde 403 com o token dele.

## 2. Banca (US1, US6)

1. Trocar a cor para Azul e o fundo para Claro, salvar → recarregar o site: botões e destaques azuis,
   fundo claro para quem não escolheu o modo no aparelho; e-mail de recuperação de senha com o botão
   azul.
2. Preencher WhatsApp `5511988887777`, Instagram; apagar Facebook → botão flutuante abre o número
   novo; ícone do Instagram aparece, o do Facebook não.
3. Desligar "Botão flutuante" → o botão some, o atalho das regras continua.
4. Link sem `https://` e WhatsApp `1199` → mensagens no campo, nada gravado.
5. GTM-ID `gtm-abc` → erro de formato; `GTM-ABC1234` → salvo.

## 3. GTM e eventos (US6)

1. Com o GTM salvo e o modo de visualização do Tag Assistant: abrir `/` → contêiner carregado; abrir
   `/painel` direto em outra aba → nenhum `gtm.js` na aba Rede.
2. Criar conta → `subscribe` uma vez.
3. Gerar Pix de R$ 50,00 → `addtocart` com `amount: 50`.
4. Fechar o modal, aprovar o pagamento (sandbox/simulação da spec 007) → `purchase` com `amount: 50`
   em até ~10s, uma vez; reenviar o aviso do Mercado Pago → nenhum `purchase` novo.

## 4. Logo e ícones (US2)

1. Enviar PNG 512x512 → prévia nova, grade de ícones; `/manifest.webmanifest` com os 8 ícones em
   `/storage/icones/{hash}/`; favicon e `apple-touch-icon` do HTML apontando para a pasta nova; a
   pasta anterior some do disco.
2. Enviar imagem retangular (1000x300) → ícones quadrados sem esticar (bordas transparentes).
3. Enviar 100x100 → salva com o aviso de imagem pequena.
4. Enviar PDF → "logo inválida"; a logo atual continua.

## 5. Banners e avisos (US3)

1. Cadastrar 2 banners com link; descer o primeiro → ordem invertida no carrossel.
2. Desativar um → some do site, continua no painel; desativar todos → carrossel some.
3. Excluir com confirmação → some da lista.
4. Cadastrar aviso com período de hoje → aparece ao abrir o site até "Lido".

## 6. Regras (US4)

1. Antes de migrar, anotar o texto; depois → mesmo texto em parágrafos em `/regras`.
2. Título, lista e negrito → `/regras` igual à prévia.
3. Colar `<img src=x onerror=alert(1)><script>alert(1)</script>` (pelo Postman no `PUT`) → o HTML
   devolvido não tem `script` nem `onerror`; nada executa em `/regras`.
4. Apagar tudo e salvar → bloco some.
5. Editar e trocar de aba → pergunta antes de perder.

## 7. Integrações (US5)

1. Mercado Pago desvinculado → "Vincular" abre a autorização; ao voltar, "Vinculado" com datas.
2. "Desvincular" → "Desvinculado"; o depósito por Mercado Pago deixa de ser oferecido.
3. Robô: "Conectar" → QR; esperar 40s sem ler → QR renovado; ler → "Conectado" com número em até 5s.
4. Desconectar pelo celular → a situação vira "Desconectado" sem abrir a aba (conferir no banco ou
   reabrindo a aba).
5. `POST /api/webhooks/evolution` sem `x-webhook-token` → 401; com outra `instance` → 404.
6. Parar a Evolution → aba mostra "Serviço do robô indisponível"; o Mercado Pago continua.
7. Supervisor → vê situações, sem botões.

## 8. Operação (US7)

1. Pagamentos: depósito mínimo 20 → modal de depósito pede mínimo R$ 20,00.
2. Sorteio: mínimo 2,00 e máximo 1,50 → erro no campo.
3. Aviso de combinação: chave não aparece, só "definida (final XXXX)"; trocar a chave.
4. Notificações manuais: Supervisor vê o limite desabilitado; Admin altera.
5. Ao vivo: trava geral aparece só como informação.

## 9. Histórico (FR-003a)

`select * from configuracoes_historicos order by id desc` → uma linha por salvamento com só os
campos mudados (antes/depois); banners/avisos com `item_id`; a chave do CallMeBot como `***` /
`alterada`; salvar sem mudar nada não gera linha.

## 10. Postman

A coleção `docs/postman/wssports_api.postman_collection.json` tem as rotas novas (banca, ao-vivo,
sorteio-cotacoes, robo-whatsapp, conectar, webhooks/evolution).
