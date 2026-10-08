# Quickstart: validação manual de Confrontos

**Feature**: `003-confrontos` | **Plano**: [plan.md](plan.md)

Sem testes automatizados (constituição). Validação por requisições HTTP e pela coleção do Postman
regenerada (`docs/postman/wssports_api.postman_collection.json`). Campos e respostas:
[data-model.md](data-model.md), [contracts/api.md](contracts/api.md) e
[contracts/provedor.md](contracts/provedor.md).

## Pré-requisitos

- Specs 001 e 002 aplicadas (Admin `admin` / `password`, hierarquia de exemplo, clientes de
  exemplo).
- MySQL 8.4.
- **Provedor simulado**: um mock server do Postman com os cinco exemplos de
  [contracts/provedor.md](contracts/provedor.md), no formato do provedor (listas, com `fonte_id`,
  `casa`, `horario`...): `GET /api/campeonatos`, `POST /api/confrontos`, `POST /api/cotacao`,
  `POST /api/aovivo` e `GET /bet/v2/confrontos/:fonte_id`. Ajuste o `horario` dos exemplos para
  hoje, amanhã e depois de amanhã (UTC).
- No `.env`:

  ```dotenv
  PROVEDOR_COTACOES_URL_PRE_JOGO=https://<mock>/api
  PROVEDOR_COTACOES_URL_AO_VIVO=https://<mock>/api
  PROVEDOR_COTACOES_URL_CONFERENCIA=https://<mock>/bet/v2
  PROVEDOR_COTACOES_CHAVE=chave-local
  ```

## 1. Atualizar o banco (sem apagar nada do legado)

```bash
php artisan migrate
php artisan db:seed --class=PapeisPermissoesSeeder
php artisan db:seed --class=ConfrontosSeeder
```

⚠️ Não use `migrate:refresh` nem `migrate:fresh` ([research.md](research.md) R-20).

**Esperado**:

- 17 tabelas novas ([data-model.md](data-model.md));
- `clientes_configuracoes_padrao` apagada;
- `clientes_configuracoes` com valores padrão nas colunas;
- 1 registro em `configuracoes`, `visitantes_configuracoes` e `confrontos_teto_cotacoes`;
- 1 linha em `usuarios_configuracoes` por vendedor existente;
- 21 permissões novas, distribuídas por função;
- a permissão `clientes.editar_configuracoes_padrao` removida.

## 2. Conferir rotas e agendamento

```bash
php artisan route:list --path=api
php artisan schedule:list
```

**Esperado**: `GET api/publico/confrontos` mais as 37 rotas do painel do contrato, e nenhuma rota
`clientes-configuracoes-padrao`. O agendamento mostra `campeonatos:importar` (`*/10 * * * *`),
`confrontos:importar` (`1-59/5 * * * *`), `confrontos_cotacoes:importar` (`2-59/5 * * * *`),
`confrontos_ao_vivo:importar` (5 s) e `confrontos_ao_vivo:conferir` (1 min).

## 3. Roteiro

Tokens: `token_admin` (painel, `admin`), `token_gerente`, `token_vendedor` (hierarquia de
exemplo) e `token_cliente` (área do cliente).

### Carga do pré-jogo

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 1 | `php artisan campeonatos:importar`, `confrontos:importar` e `confrontos_cotacoes:importar`, nessa ordem, com o mock devolvendo 2 campeonatos e 3 confrontos | registros criados com cotações, jogadores e `quantidade_cotacoes`; cada carga < 5 s | US1-1, SC-001 |
| 2 | Mudar `odd1` no mock e rodar `confrontos_cotacoes:importar` | mesmo confronto atualizado, sem duplicar | US1-2 |
| 3 | Confronto de futebol com `odd4: 0`; rodar as cotações duas vezes | `odd4` entre 1,30 e 1,50, `odd4_sorteada = 1`, mesmo valor nas duas | US1-4 |
| 4 | Mock passa a mandar `odd4: 2.05` | `odd4 = 2.05`, `odd4_sorteada = 0` | US1-5 |
| 5 | Basquete com `odd4: 0` | `odd4` continua ausente | US1-6 |
| 6 | Desativar um campeonato (rota do painel) e rodar confrontos, cotações e ao vivo | o corpo enviado ao mock traz o `codigo_externo` em `campeonatos_id` (ver o log do mock) | US1-7 |
| 7 | Campeonato com porcentagem e não permitido; mock manda o mesmo nome e país com outro `fonte_id`; rodar `campeonatos:importar` | regras movidas para o campeonato novo | US1-8 |
| 8 | Mock devolvendo 500, depois um objeto no lugar da lista, em cada rota | nenhum dado daquela carga muda; falha no `storage/logs/laravel.log`, sem a chave nem a URL | US1-9, FR-053 |
| 9 | `UPDATE configuracoes SET somente_cassino = 1` e rodar as três | os comandos terminam sem chamar o mock | US1-10 |
| 10 | `permitir_entrada_campeonatos = 0` e mock com campeonato novo | criado com `ativo = 0`; os antigos não mudam | US1-14 |
| 11 | Cotação com valor negativo ou texto | só esse confronto ignorado, motivo no log | Edge |
| 11a | Mock com confronto de campeonato novo: rodar confrontos antes dos campeonatos, depois na ordem | primeiro ignorado ("sem campeonato" no resumo), depois gravado | US1-15 |
| 11b | Mock com cotação de confronto novo: rodar cotações antes dos confrontos, depois na ordem | primeiro ignorada ("ainda inexistentes" no resumo), depois gravada | US1-16 |
| 11c | Mudar `casa` no mock e rodar `confrontos:importar` | `time_casa` muda; cotações e jogadores intactos | US1-17 |

### Listagem pública (pré-jogo)

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 12 | `GET /api/publico/confrontos` sem token | jogos de hoje, agrupados por campeonato, `odd1`–`odd4`, `paises`, `total`, `token_recusado: false` | US2-1 |
| 13 | `dia=amanha`, `dia=depois_de_amanha`, `dia=ontem` | só o dia pedido; `422` em `ontem` | US2-2, US2-4 |
| 14 | Jogo às 01:00 UTC de amanhã: `fuso_horario=-03:00` e `+00:00` | aparece em hoje às 22:00 (−03:00); só em amanhã (+00:00) | US2-3 |
| 15 | `busca=` time com jogo amanhã e em 5 dias | só o de amanhã | US2-5, SC-011 |
| 16 | `esporte=BASQUETE`; `somente_favoritos=1` | só basquete; só favoritos | US2-6, US2-7 |
| 17 | `fuso_horario=abc`; `por_pagina=101` | `422` | US2-10 |
| 18 | Token expirado de cliente | resposta de visitante com `token_recusado: true` | US2-11 |
| 19 | 121 requisições em 1 minuto do mesmo IP | a 121ª recebe `429` | FR-044 |
| 19a | Campeonatos de países diferentes (um favorito), com nomes intercalados em ordem alfabética; repetir com `tipo=ao_vivo` | favorito no topo; depois os campeonatos agrupados por país em ordem alfabética, e dentro do país por nome; mesma ordem do índice `paises` | FR-042 |
| 19b | Sem token: `GET /api/publico/confrontos?tipo=ao_vivo` com o provedor mandando `tipo_esporte` `FUTEBOL AO VIVO` | jogos listados; em `confrontos_ao_vivo` o `esporte` gravado é `FUTEBOL` | FR-014 |
| 19c | Visitante com `HOQUEI NO GELO` nos permitidos: `esporte=HÓQUEI NO GELO&dia=amanha` | jogos de hóquei listados | FR-041 |

### Cotação ajustada

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 20 | `PATCH /api/porcentagens-clientes` `{"tipo":"pre_jogo","valores":{"odd1":-10}}`; listar como visitante um jogo com `odd1` 2,00 | `1.80` | US3-1, US8 |
| 21 | Regra do cliente `{"clientes_id":X,"tipo":"pre_jogo","valores":{"odd1":-10}}`; listar com `token_cliente` de X | `1.60` | US3-2 |
| 22 | Supervisor −4, gerente −3, vendedor −2 e campeonato −1 (alvo Vendedores); listar com `token_vendedor` | `1.80` | US3-3 |
| 23 | `PATCH /api/porcentagens-confrontos/{id}` `{"alvo":"Clientes","cotacoes":{"odd1":1.92}}` com regra de clientes −10 | valor fixo −0,08 gravado; visitante vê `1.72` | US3-4, US8-5 |
| 24 | Teto `odd1` 250 e base que daria 300; base que daria 0,95; `odd2` ausente | `250.00`; `1.00`; `0` | US3-5 a US3-7 |
| 25 | Conferir o JSON da listagem | sem porcentagens, valores fixos, tetos nem cotação do provedor | US3-8, SC-008 |

### Ao vivo

Em outro terminal: `php artisan schedule:work`.

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 26 | Mock do ao vivo com um jogo que existe no pré-jogo; `GET /api/publico/confrontos?tipo=ao_vivo` | placar, minuto, cronômetro, `travado: false` | US4-1, US5-1 |
| 27 | Parar o `schedule:work` e esperar 15 s | mesmo jogo com cotações 0 e `travado: true` | US4-3, US4-5, SC-004 |
| 28 | Voltar o `schedule:work` | cotações voltam | US4-6 |
| 29 | Esperar mais de 5 min parado | o jogo some da listagem | US5-4 |
| 30 | Jogo do ao vivo sem confronto no pré-jogo; jogo no minuto 96 | não aparecem | US5-5, US5-6 |
| 31 | Porcentagem de clientes do ao vivo −20 | ajuste −20 aplicado (não o do pré-jogo) | US5-2 |
| 32 | Cotação ajustada 45,00 | exibida `30.00` | US5-7 |
| 33 | `UPDATE configuracoes SET ao_vivo_habilitado = 0` | `tipo=ao_vivo` → `403`; pré-jogo continua | US5-8 |

### Conferência

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 34 | Jogo no minuto 30 e mock da conferência com `minuto_exato` 33; `php artisan confrontos_ao_vivo:conferir` | `ao_vivo_travado = 1`; todo o ao vivo zerado; log com os dois minutos | US6-1, US6-2 |
| 35 | Mock com 30 e rodar de novo | liberado; log da liberação | US6-3 |
| 36 | Mock fora do ar | `ao_vivo_travado` não muda; falha no log | US6-4 |

### Painel: campeonatos, confrontos e não permitidos

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 37 | `POST /api/campeonatos` (manual) e `POST /api/confrontos` para amanhã com 3 cotações | `201`; aparece na listagem de amanhã; `quantidade_cotacoes = 3` | US9-1, US9-2 |
| 38 | Rodar as três cargas do pré-jogo | manuais intactos | US9-4, SC-015 |
| 39 | `PUT` num confronto do provedor; confronto manual sem cotação ou com data passada | `422` | US9-6, US9-7 |
| 40 | `DELETE /api/campeonatos/{manual}` | campeonato e confrontos somem (soft delete) | US9-8 |
| 41 | Gerente: `POST /api/confrontos-nao-permitidos` `{"confrontos_id":X,"alvo":"Vendedores"}` | some para os vendedores dele; aparece para outro gerente e para visitante | US7-2, US10-5 |
| 42 | Admin: não permitido de campeonato com `alvo: Clientes` e `clientes_id` de um cliente | some só para esse cliente | US7-6 |
| 43 | Admin: `alvo: Todos` | some para todo mundo | US7-5 |
| 44 | Gerente com `alvo: Clientes`; Gerente em `PATCH /api/campeonatos/{id}/situacao` | `403` | US10-7 |
| 45 | Admin: desativar campeonato; favoritar outro | some para todos; o favorito vem primeiro | US10-2, US10-4 |

### Configurações

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 46 | Supervisor: `PATCH /api/usuarios-configuracoes` `{"usuarios_id":<gerente>,"ao_vivo_habilitado":false}` | `vendedores_alterados` = vendedores do gerente; eles recebem `403` no ao vivo | US11-2 |
| 47 | Gerente cadastra vendedor novo (rota da spec 001) | a linha do novo copia a de um colega (ao vivo desligado) | US11-5, SC-017 |
| 48 | Gerente altera vendedor de outro gerente | `403` | US11-4 |
| 49 | Admin: `PUT /api/visitantes-configuracoes` com `ao_vivo_habilitado: false` | visitante recebe `403` no ao vivo; cliente e vendedor não | US11-7 |
| 50 | Cadastrar cliente novo (rota da spec 002) | configuração criada com os valores padrão das colunas | US11-8 |
| 51 | `GET /api/clientes-configuracoes-padrao` | `404` (rota removida) | FR-079 |
| 52 | Spec 004: vendedor com `periodo_jogos: Hoje` lista `dia=amanha` | lista vazia | FR-065 (004) |
| 53 | Spec 004: `data_travamento_sistema` do visitante no passado | listagem do pré-jogo e do ao vivo vazia | FR-019 (004) |
| 54 | Spec 004: confronto futuro com registro em andamento em `confrontos_ao_vivo` | some da listagem do pré-jogo | FR-017 (004) |

## 4. Fechamento

- Coleção do Postman regenerada com a pasta "Confrontos" (rotas novas) e sem as rotas da tabela
  padrão.
- Documentos da spec 002 atualizados (FR-080).
