# Quickstart: validação manual de Apostas

**Feature**: `004-apostas` | **Plano**: [plan.md](plan.md)

Sem testes automatizados (constituição). Validação por requisições HTTP e pela coleção do Postman
regenerada (`docs/postman/wssports_api.postman_collection.json`). Campos e respostas:
[data-model.md](data-model.md) e [contracts/api.md](contracts/api.md).

## Pré-requisitos

- Specs 001, 002 e 003 aplicadas (Admin `admin` / `password`, hierarquia de exemplo com Supervisor,
  Gerente e Vendedor, clientes de exemplo e jogos carregados do provedor simulado da spec 003).
- MySQL 8.4 e PHP com `bcmath`.
- Agendador e fila rodando:

  ```bash
  php artisan schedule:work
  php artisan queue:work --queue=apostas,default
  ```

## 1. Atualizar o banco (sem apagar nada do legado)

```bash
php artisan migrate
php artisan db:seed --class=PapeisPermissoesSeeder
php artisan db:seed --class=ApostasSeeder
```

⚠️ Não use `migrate:refresh` nem `migrate:fresh` (R-20 da spec 003).

**Esperado**:

- 5 tabelas novas (`apostas`, `apostas_palpites`, `apostas_historico`, `clientes_rollovers`,
  `apostas_rollovers`);
- colunas novas com os padrões em `usuarios_configuracoes`, `clientes_configuracoes`,
  `visitantes_configuracoes`, `configuracoes`, `confrontos` e `confrontos_ao_vivo`;
- vendedores existentes com `apostas.criar`, `apostas.validar` e `apostas.cancelar`; gestores com
  `apostas.cancelar`, `apostas.cancelar_iniciada` e `apostas.editar`; Admin e Supervisor com
  `confrontos.alterar_limite`.

## 2. Preparar os dados

1. Escolha 3 jogos do pré-jogo de hoje (`GET /api/publico/confrontos`) e anote `id` e as cotações
   `odd1` vistas pelo vendedor (com o token dele) e pelo visitante (sem token).
2. Com o Admin, configure o vendedor: `PATCH /api/usuarios-configuracoes` com `comissao_pre_jogo_2:
   10`, `ganho_multiplo_palpites: 10` e `tempo_cancelamento_aposta: 5`.

## 3. Cenários

| # | Cenário | Como | Esperado |
|---|---|---|---|
| 1 | Vendedor aposta (US1) | `POST /api/apostas`, R$ 10,00 em 2 jogos | 201, `Ativa`, prêmio = 10 × cotações, comissão R$ 1,00; `limite_duplo` e `limite_geral` −10 |
| 2 | Aposta de 1 jogo | R$ 10,00 em 1 jogo | `limite_simples` e `limite_geral` −10 |
| 3 | Idempotência | repetir o cenário 1 com a mesma chave | 200, mesma aposta, limites sem novo débito |
| 4 | Jogo repetido | 2 palpites no mesmo confronto | 422 "Não é possível cadastrar jogos repetidos na aposta" |
| 5 | Jogo iniciado | alterar `data_inicio` de um jogo para o passado no banco e apostar | 422 "...já iniciou, retire-o para concluir" |
| 6 | Jogo no ao vivo | criar registro em `confrontos_ao_vivo` para um jogo futuro e apostar como pré-jogo | 422; o jogo some da listagem do pré-jogo |
| 7 | Cotação alterada (US5) | enviar `cotacao_vista` diferente da atual com `Nenhuma` | 409 com `alteracoes`; reenviar com a atual → 201 |
| 8 | Somente para maior | `cotacao_vista` menor que a atual | 201 com a atual |
| 9 | Cotação indisponível | zerar a cotação no JSON `cotacoes` e apostar | 422 com `indisponiveis`; nenhuma aposta com 1,00 |
| 10 | Prêmio e acréscimo (US7) | 3 jogos, prêmio perto do prêmio máximo | acréscimo de 10% limitado ao prêmio máximo |
| 11 | Multiplicador | multiplicador 1000, R$ 5,00 com cotação total alta | prêmio ≤ R$ 5.000,00 |
| 12 | Limite por confronto | `PATCH /api/confrontos/{id}/limite` = 15,00 e apostar R$ 10,00 duas vezes | 2ª: 422 "Restam R$ 5,00 de limite..." |
| 13 | Cliente com saldo real (US2) | cliente com R$ 50 real e R$ 20 bônus aposta R$ 30 | `forma_pagamento: Saldo`; transação Aposta/Débito/Saldo |
| 14 | Cliente usa o bônus | real R$ 5, bônus R$ 20, aposta R$ 10 | `Promoção esportes` |
| 15 | Sem combinar | real R$ 5, bônus R$ 8, aposta R$ 10 | 422 "Você não tem saldo suficiente..." |
| 16 | Concorrência | 2 apostas de R$ 10 simultâneas com R$ 10 de saldo (2 abas do Postman) | uma 201, outra 422 |
| 17 | Regras do bônus (US8) | cliente novo com bônus de Primeiro cadastro (rollover 5, mínimo R$ 5) aposta R$ 2 com o bônus | 422 com a regra; com R$ 10 → `clientes_rollovers.valor_apostado` = 10 |
| 18 | Visitante gera código (US3) | `POST /api/publico/apostas` sem token | 201, `Pendente`, código de 8 caracteres |
| 19 | Visitante no ao vivo | palpite com `confrontos_ao_vivo_id` | 422 "Para apostar no ao vivo é preciso fazer login." |
| 20 | Simulação (US4) | `GET /api/apostas/pendentes/{codigo}` com o vendedor | cotações do vendedor; nada gravado |
| 21 | Validação | `POST .../validar` com as cotações da simulação | 200, mesma aposta `Ativa`, `validada_em`, limites abatidos |
| 22 | Cotação mudou na validação | alterar a cotação entre a simulação e a validação | 409 com o novo prêmio |
| 23 | Validar duas vezes | repetir a validação | 404 "Aposta não encontrada ou já validada." |
| 24 | Expiração | `expira_em` no passado (banco) e aguardar 1 min | `Expirada`; validação recusada |
| 25 | Ao vivo aceito (US6) | cliente aposta num jogo ao vivo (`delay_ao_vivo` 15); a carga do ao vivo atualiza sem mudar placar | 202 `Em análise`; após ~15 s `/situacao` mostra `Ativa` e o saldo é debitado |
| 26 | Ao vivo recusado por lance | mudar o placar no provedor simulado durante o delay | `Recusada` com motivo; nada debitado |
| 27 | Ao vivo sem atualização | parar a carga do ao vivo durante o delay | `Recusada` |
| 28 | Uma análise por vez | enviar outra aposta ao vivo durante o delay | 422 |
| 29 | Análise presa | parar o worker da fila durante o delay e aguardar delay + 60 s | `Recusada` pelo comando |
| 30 | Cancelar no tempo (US9) | vendedor cancela a aposta do cenário 1 em 1 min | 200 `Cancelada`; limites devolvidos |
| 31 | Cancelar fora do tempo | vendedor cancela após 6 min | 422 "O seu tempo de 5 minuto(s)..." |
| 32 | Gerente cancela | gerente do vendedor cancela outra aposta dele | 200; de vendedor de outro gerente → 404 |
| 33 | Cancelar aposta de cliente | Gerente cancela a aposta do cenário 13 | saldo real devolvido (Estorno); rollover desfeito |
| 34 | Aposta apurada | marcar `resultado = Vencedor` no banco e cancelar com o Admin | 422 "Não é possível cancelar uma aposta já apurada" |
| 35 | Cliente não cancela | rota de cancelar com token de cliente | 401 (rota só do painel) |
| 36 | Editar palpite (US10) | Admin cancela o palpite de 1,50 de uma aposta de 3 jogos | prêmio recalculado; linha em `apostas_historico`; assinatura nova |
| 37 | Restaurar palpite | restaurar o mesmo palpite | prêmio volta ao anterior |
| 38 | Último palpite | cancelar o único palpite ativo | 422 |
| 39 | Comprovante (US11) | `GET /api/publico/apostas/{codigo}` | campos do contrato; sem porcentagens, IPs nem comissão |
| 40 | Vários códigos | `POST /api/publico/apostas/consultar` com 3 códigos (1 inexistente) | 2 itens |
| 41 | Período de jogos (US12) | `periodo_jogos: Hoje` no vendedor; listar e apostar num jogo de amanhã | jogo some da listagem; aposta 422 |
| 42 | Data de travamento | `data_travamento_sistema` no passado | listagem vazia; aposta, validação e cancelamento recusados |
| 43 | Apostar em jogador | `apostar_jogadores: false` e palpite `jogador` | 422 |
| 44 | Força bruta | 61 consultas de código em 1 min | 429 |
| 45 | Detalhe do confronto | `GET /api/publico/confrontos/{id}` sem token e com token do vendedor | todas as cotações disponíveis com nome do mercado; valores diferentes por público; cotação de `odd5` igual à aceita numa aposta em `odd5` |
| 46 | Detalhe não visível | detalhe de um confronto não permitido ou de amanhã com `periodo_jogos: Hoje` | 404 |
| 47 | Detalhe do ao vivo travado | parar a carga do ao vivo e pedir o detalhe após 15 s | `travado: true` e cotações `0.00` |
| 48 | Adulteração | alterar `premio` no banco e conferir a assinatura (tinker: `app(AssinaturaApostas::class)->conferir($aposta)`) | `false` |

## 4. Coleção do Postman

Importar `docs/postman/wssports_api.postman_collection.json` e conferir a pasta "Apostas" com as
16 rotas novas e as variáveis `base_url`, `token` e `token_cliente`.
