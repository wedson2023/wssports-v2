# Quickstart: validação manual do painel e da Dashboard

Sem testes automatizados (Constituição). Contratos em [contracts/api.md](contracts/api.md) e
[contracts/paginas.md](contracts/paginas.md); regras em [research.md](research.md).

## Preparação

```bash
composer install && npm install        # chart.js entra pelo package.json
php artisan migrate
php artisan db:seed --class=DashboardSeeder   # permissões dashboard.* para os usuários existentes
npm run build                            # ou npm run dev
php artisan schedule:work                # em outro terminal, para o aviso de combinação
```

Usuários de teste (seed): `admin` (Admin), supervisor, gerente e 2 vendedores, senha `password`.
Para os cenários de rede, use um segundo gerente com vendedor próprio (cadastrado pela API de
usuários).

## 1. Login e acesso (US1)

1. Entrar como `admin` pelo modal → vai para `/painel`, Dashboard aberta, sem botão do Mercado Pago.
2. Repetir com o supervisor e o gerente → `/painel`.
3. Entrar como vendedor → continua em `/`; tocar em "Olá, Nome" → `/painel`.
4. No painel: APOSTAR volta para `/` logado; SAIR encerra a sessão.
5. Deslogado, abrir `/painel` → volta para `/`. Repetir logado como cliente e como afiliado.
6. Celular (DevTools ≤ 900 px): ☰ abre a gaveta; fecha ao tocar fora, escolher item ou voltar.

## 2. Números e alcance (US2)

1. Fazer apostas do dia com vendedores do gerente A e do gerente B (validadas, em dinheiro).
2. Gerente A: Apurado, Apostas e Situação dos jogos somam só os vendedores dele (conferir à mão).
3. Vendedor: vê só as dele e os 3 limites (cadeado fechado com limite 0).
4. Admin: Apurado já carregado; Depósitos online = depósitos aprovados do dia, somados às
   Entradas; saques pagos nas Saídas.
5. Lançamentos, Retiradas e Comissão do gerente: R$ 0,00 com selo "em breve".
6. Aposta com dois palpites (Criar Aposta) no mesmo jogo → Situação dos jogos conta 1 jogo.
7. Cancelar uma aposta → sai das Entradas e aparece em Canceladas.
8. Aposta de cliente pelo site → não aparece no Apurado; aparece na Caixa online.

## 3. Risco (US3)

1. Três bilhetes abertos com os mesmos jogos e opções (vendedores do gerente A) → linha com 3
   bilhetes para o gerente A e o Admin; não aparece para o gerente B; seção some para o vendedor.
2. 👁 → lista dos 3 códigos → cada um abre o bilhete.
3. Bilhete com só um jogo ainda em aberto → aparece em Apostas por um jogo (até 50, por prêmio).
4. Com a Dashboard aberta, criar uma combinação nova → em até 1 min balão + som + linha destacada;
   recarregar a página → não avisa de novo; mais um bilhete na mesma combinação → sem aviso.
5. `GET /dashboard/combinacoes-risco` com o token do vendedor → 403.

## 4. Aviso no WhatsApp (US3, FR-045 a FR-049)

1. `PUT /configuracoes/avisos-whatsapp` com `aviso_combinacao_whatsapp: true` sem telefone → 422.
2. Gravar telefone e chave do CallMeBot e ligar → `GET` mostra `chave_callmebot_definida: true` e
   só os 4 últimos caracteres.
3. Criar combinação nova com o painel fechado → mensagem no WhatsApp em até 2 min;
   `apostas_combinacoes_risco.situacao_whatsapp = Enviado`.
4. Duas combinações novas no mesmo minuto → uma mensagem com as duas.
5. Chave errada → `tentativas_whatsapp` sobe a cada minuto até 3 e fica `Falhou`; a Dashboard
   segue normal.
6. Aviso desligado → combinação nova fica `Dispensado`.

## 5. Rankings e Caixa online (US4, US5)

1. 12 vendedores com vendas → Vendedores mostra 10, do maior para o menor.
2. Jogos e Opções mais apostados batem com a contagem manual; nomes das opções legíveis.
3. Caixa online (Admin): entradas só com saldo real (aposta com saldo de promoção fora);
   encerramentos = cash outs do dia; Saldo líquido = entradas − saídas − encerramentos.
4. Gerente e vendedor: Caixa online não aparece; API → 403.

## 6. Permissões (US6)

1. Supervisor recém-cadastrado → todas as seções.
2. Admin retira `dashboard.ver_combinacao_risco` do supervisor
   (`DELETE /usuarios/{id}/permissoes/dashboard.ver_combinacao_risco`) → seção some; API → 403.
3. Dar `dashboard.ver_caixa_online` a um vendedor → continua 403.
4. Rodar o seeder de novo → não duplica nem tira permissões.

## 7. Desempenho e UX (US7)

1. Network lento (DevTools "Slow 4G"): cada seção mostra esqueleto e aparece sozinha.
2. Forçar erro numa seção (ex.: retirar a permissão com a tela aberta) → só ela muda.
3. Dia sem movimento → "Sem movimento hoje." em todas.
4. Reabrir em menos de 1 min → seções em ≤ 1 s (cache); ⟳ recalcula (`?atualizar=1`).
5. Aba oculta por 2 min → nenhuma requisição no Network; ao voltar, atualiza.
6. Abrir `/` como visitante → no Network não é baixado o pedaço do `chart.js` (SC-007).
7. Volume: com ~5.000 apostas no dia (seed manual ou cópia do banco de teste), Apurado, Apostas e
   Situação dos jogos em ≤ 2 s (SC-003).

## 8. Postman

A coleção `docs/postman/wssports_api.postman_collection.json` tem as rotas novas de `contracts/api.md`.
