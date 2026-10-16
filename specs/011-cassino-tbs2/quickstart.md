# Quickstart: validação manual do cassino

**Feature**: `011-cassino-tbs2` | **Data**: 2026-10-16

O projeto não tem testes automatizados (constituição). Este roteiro valida a feature à mão, pelos
cenários da spec. Rotas e formatos estão em [contracts/](contracts/); tabelas em
[data-model.md](data-model.md).

## Preparação

1. No `.env` local (nunca no repositório), coloque as credenciais de teste informadas pelo
   responsável:

   ```dotenv
   CASSINO_URL_API=http://tbs2api.aslot.net/API/
   CASSINO_HALL=<hall de teste>
   CASSINO_CHAVE=<chave de teste>
   CASSINO_ASSINATURA=false
   CASSINO_PREFIXO_LOGIN=wss
   ```

2. Rode `php artisan migrate` e `php artisan db:seed --class=CassinoSeeder`.
3. Ligue o cassino: `PUT /api/configuracoes/cassino` com `{"cassino_habilitado": true}` (token de
   Admin).
4. Rode `php artisan cassinos_jogos:sincronizar` e confira o resumo (cerca de 6.365 recebidos).
5. Suba o site: `composer run dev` (Vite com o Node 20).
6. Para o provedor chamar a carteira local, exponha o `php artisan serve` com um túnel HTTPS e
   cadastre `<túnel>/api/cassino/carteira` **apenas num hall de teste**. Não altere o callback do
   hall de produção.
7. Sem túnel, simule as chamadas do provedor com o Postman (pasta "Cassino → Callback").

## US1: Navegar no cassino

| # | Passo | Esperado |
|---|---|---|
| 1 | Abrir `/cassino` no celular (DevTools) | Banners, busca, provedores (os 10 do antigo + Aviator), tipos, Populares e uma linha por tipo com até 10 jogos e "Veja mais"; visual igual ao `screens/casino` antigo |
| 2 | Buscar "Fortune Tiger" | Um único resultado, PG Soft (SC-001) |
| 3 | Buscar "Aviator" e "Mines" | Um resultado de cada, mesmo com `spribe` e `spribe_sr` no provedor |
| 4 | Tocar num provedor e depois num tipo; rolar a grade | Só jogos do filtro, carregando de 50 em 50; "Limpar" volta à página inicial |
| 5 | Abrir no computador | Só jogos com aparelho `Todos` ou `Computador` |
| 6 | `PUT /configuracoes/cassino` com `cassino_habilitado` false e recarregar `/cassino` e `/` | Mensagem "Acesso ao cassino não está disponível."; "Cassino" some da barra de esportes |

## US2: Jogar com o próprio saldo

| # | Passo | Esperado |
|---|---|---|
| 1 | Visitante toca em "Jogar" | Modal de entrar/cadastrar |
| 2 | Cliente com saldo toca em "Jogar" na Fortune Tiger | O jogo abre na moldura, com voltar e tela cheia (ou em nova página, quando `withoutFrame` = 1) |
| 3 | Abrir um jogo de canal `_sr` com o canal ativado no painel | O canal fica indisponível (`hall_balance_less_100`) e o jogo abre pelo próximo canal ou aparece "Jogo indisponível no momento." |
| 4 | Callback `getBalance` com `wss<id>` | `success` com o saldo real (duas casas) |
| 5 | `writeBet` com `action` SPIN, bet 1.00, win 0.00 | Saldo −1,00; uma rodada; uma transação `Cassino` de débito; `operationId` = id da rodada |
| 6 | Repetir o mesmo `tradeId` | Mesma resposta; saldo e rodadas inalterados (SC-003) |
| 7 | `writeBet` BET_AND_WINS bet 1.00, win 2.50 | Uma transação de crédito de 1,50; rodada com aposta 1,00 e prêmio 2,50 |
| 8 | `writeBet` com bet maior que o saldo e bônus zerado | `fail` / `fail_balance`; nada muda |
| 9 | Cliente com R$ 3,00 de saldo e R$ 3,00 de bônus aposta 5,00 | `fail_balance` (as carteiras não se somam) |
| 10 | Saldo real zerado, bônus de cassino R$ 10,00, aposta 1,00 | Sai do bônus; a sessão passa a `Promoção cassino`; `getBalance` mostra o bônus |
| 11 | Canal com `bonus_primeiro` e cliente com bônus | Primeira aposta sai do bônus; sem bônus suficiente, do saldo real |
| 12 | `writeBet` CANCEL com o `tradeId` de uma aposta | A aposta volta à carteira de origem; o rollover é desfeito; a rodada original fica cancelada |
| 13 | `writeBet` REFUND sem saldo | Crédito feito, sem conferir saldo |
| 14 | `key` errada ou `hall` errado | `fail` / `wrong_key` ou `wrong_hall`; nada muda; chamada registrada como `Recusada` (SC-005) |
| 15 | `action` XYZ | `fail` / `unknown_action`; aparece em `GET /cassinos-chamadas?resultado=Recusada` |
| 16 | Duas chamadas `writeBet` simultâneas do mesmo cliente (dois terminais com `curl`) | Ambas processadas uma após a outra; saldo final correto |
| 17 | Fechar o jogo pelo botão do jogo e pelo voltar do navegador | Volta ao ponto do cassino em que estava |
| 18 | Painel em acesso assistido toca em "Jogar" | 403 com a mensagem do acesso assistido |

## US3: Rodadas na Minha Conta

| # | Passo | Esperado |
|---|---|---|
| 1 | Depois de duas sessões, abrir `/minha-conta` | Uma linha por sessão com data, "Cassino", total apostado e prêmio preenchido |
| 2 | Filtro "Cassino" | Só as sessões |
| 3 | Tocar numa linha | Modal com cada rodada: data, ID, saldo antes, aposta, prêmio e carteira (SC-007) |
| 4 | Desligar o cassino e abrir a Minha Conta | Card "Bônus cassino" e filtro Cassino escondidos |

## US4: Gerir o catálogo

| # | Passo | Esperado |
|---|---|---|
| 1 | `POST /cassinos-sincronizacoes` e `GET /cassinos-sincronizacoes` | Carga `Concluída` com o resumo |
| 2 | Desativar um provedor, um jogo e um canal (`PUT`) | O provedor e o jogo somem da página; com o canal desativado, o jogo abre pelo próximo |
| 3 | Marcar um jogo `popular` e mudar a `ordem` | Ele aparece em Populares na ordem nova |
| 4 | Conferir um canal `_sz` no `GET /cassinos-jogos` | `pre_pago` e `bonus_primeiro` marcados e `ativo` false |
| 5 | Simular a sincronização com a resposta vazia (credencial errada no `.env`) | Carga `Falha`; nenhum jogo desativado |

## US5: Relatórios

| # | Passo | Esperado |
|---|---|---|
| 1 | `GET /cassinos-rodadas/relatorio?data_inicial=…&data_final=…` | Resumo e itens por jogo com rodadas, entradas, saídas, GGR e comissão pela porcentagem do provedor |
| 2 | `agrupar=provedor` e `agrupar=canal` | Mesmos totais, agrupados de outra forma |
| 3 | Alterar a porcentagem de um provedor | O relatório usa o valor novo |
| 4 | `GET /cassinos-sessoes/{sessao}/historico` | Conteúdo do `gameSessionsLog` |

## Fechamento

1. Conferir no banco que a soma das rodadas de cada sessão bate com a soma das transações `Cassino`
   dela (SC-003).
2. Medir o callback nos logs (`duracao_ms` de `cassinos_chamadas`): 99% abaixo de 2 s (SC-004).
3. Rodar `php artisan cassinos_chamadas:limpar` e conferir a retenção.
4. Informar ao responsável a URL de callback: `https://<domínio>/api/cassino/carteira`.
