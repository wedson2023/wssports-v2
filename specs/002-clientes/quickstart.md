# Quickstart: validação manual de Clientes

**Feature**: `002-clientes` | **Plano**: [plan.md](plan.md)

Sem testes automatizados (constituição). Validação por requisições HTTP, pela coleção do Postman
regenerada (`docs/postman/wssports_api.postman_collection.json`). Campos e respostas:
[data-model.md](data-model.md) e [contracts/api.md](contracts/api.md).

## Pré-requisitos

- Spec 001 aplicada (usuários, JWT e spatie funcionando; Admin `admin` / `password`).
- Cliente HTTP com `Accept: application/json`.
- Um terminal separado com `php artisan queue:work` rodando (necessário para o estorno).

## 1. Atualizar o banco (sem apagar nada)

```bash
php artisan migrate
php artisan db:seed --class=ClientesSeeder
```

⚠️ Não use `migrate:refresh` nem `migrate:fresh` ([research.md](research.md) R-20).

**Esperado**: 7 tabelas novas (`clientes`, `clientes_transacoes`, `clientes_configuracoes`,
`clientes_configuracoes_padrao`, `clientes_meios_pagamento`, `clientes_promocoes`,
`clientes_codigos_recuperacao`); 1 registro de configurações padrão com os valores do data-model;
10 permissões novas no guard `api`, dadas ao Admin; clientes de exemplo; tabelas existentes
intactas.

## 2. Conferir as rotas

```bash
php artisan route:list --path=api/area_cliente
php artisan route:list --path=api/clientes
```

**Esperado**: 15 rotas em `area_cliente` e 23 em `clientes`/`clientes_*` (sem `store` em
`clientes`).

## 3. Roteiro

Tokens: `token_admin` (painel, `admin`) e `token_cliente` (área do cliente). Para os testes de
permissão, dê ou tire permissões pelas rotas da spec 001.

### Cadastro e login

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 1 | `POST /api/area_cliente/cadastro` com dados válidos, sem CPF e sem e-mail, sem promoção ativa | `201` com token; 3 saldos `"0.00"`; configurações iguais ao padrão; log "WhatsApp" de boas-vindas | US1-1, US1-5 |
| 2 | Cadastro com telefone repetido (com máscara `(11) 98888-7777`); com CPF repetido; com e-mail repetido em maiúsculas | `422` de duplicidade em cada caso | US1-3, US1-4, Edge |
| 3 | Dois cadastros sem CPF e sem e-mail | ambos `201` (vazios não conflitam) | Edge |
| 4 | CPF inválido; e-mail inválido; nascido há 17 anos; senha `abcdefgh`; confirmação diferente | `422` com a mensagem de cada caso | US1-6 a US1-8 |
| 5 | Cadastro sem `ddi`; depois 6 cadastros seguidos do mesmo IP em 1 minuto | `ddi = "55"`; o 6º recebe `429` | US1-9, FR-001a |
| 6 | Criar promoção `"Primeiro cadastro"`, `"Esportes"`, `"Fixo"`, `20.00` e cadastrar um cliente | `saldo_promocao_esportes = "20.00"` e transação `"Promoção"` com `referencia_id` | US1-2, FR-075 |
| 7 | Cadastrar com `aceita_promocao: false` | saldos promocionais `"0.00"`, sem transação; `aceita_promocao` `false` nas configurações | US1-12 |
| 8 | Login certo; senha errada; 6 erradas em 1 min | `200`; `401` genérico; `429` | US2-1, US2-2, US2-7 |
| 9 | `token_cliente` em `GET /api/usuarios`; `token_admin` em `GET /api/area_cliente/meus_dados` | `401` nos dois | US2-4 |
| 10 | `refresh` e `logout`; reutilizar o token após o logout | novo token; `204`; `401` | US2-5, US2-6 |

### Recuperação de senha

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 11 | `recuperar_senha` com telefone cadastrado e com não cadastrado | mesma resposta `200`; código só no log do primeiro | US3-1, US3-2 |
| 12 | Pedir de novo em menos de 1 min | `429` | US3-6 |
| 13 | `redefinir_senha` com código errado 5 vezes; depois com o certo | `422`; o código foi invalidado | US3-5 |
| 14 | Novo pedido e `redefinir_senha` com o código do log | `204`; tokens antigos `401`; login só com a nova senha; reusar o código → `422` | US3-3, US3-4 |

### Área do cliente

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 15 | `GET meus_dados` | dados, 3 saldos e `aceita_promocao`, sem `password` | US5-1 |
| 16 | `PATCH meus_dados` com `nome`, `email` e `aceita_promocao`; depois com `cpf` | `200`; depois `422` | US5-2, US5-3 |
| 17 | `PUT meus_dados/senha` com senha atual errada; depois certa | `422`; depois `204` e o token atual passa a `401` | US5-4 |
| 18 | `GET meus_dados/extrato` com período; data inicial > final | só o período, mais recente primeiro, sem `autor`; `422` | US5-5, US5-6 |

### Meios de pagamento

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 19 | Cadastrar Pix `"CPF"` com CPF válido | `201`, `principal: true` | US9-1 |
| 20 | Cadastrar Transferência bancária completa | `201`, `principal: false` | US9-2 |
| 21 | Pix `"E-mail"` com um telefone como chave; Pix repetido | `422` nos dois | US9-3, US9-4 |
| 22 | `PATCH` na transferência com `principal: true` | ela vira principal e o Pix deixa de ser | US9-5 |
| 23 | Outro cliente tenta `GET` o meio do primeiro | `404` | US9-6 |
| 24 | Excluir o principal | o mais antigo restante vira principal | Edge |
| 25 | Gerente sem `clientes.ver_dados_completos` em `GET /api/clientes/{id}/meios_pagamento` | chave e conta mascaradas | US9-7 |

### Saldos pelo painel

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 26 | Crédito `100.00` em `"Saldo"`, depois débito `30.00` | saldo `70.00`; transações `0.00→100.00` e `100.00→70.00`, autor Admin | US4-1, US4-2 |
| 27 | Débito de `100.00` | `422 "Saldo insuficiente."`, saldo mantido | US4-3 |
| 28 | Crédito em `"Promoção cassino"` | só essa carteira muda | US4-4 |
| 29 | Valor `0`, `-5`, `10.555` ou sem `observacao` | `422` | US4-7 |
| 30 | 2 débitos de `10.00` ao mesmo tempo | saldo final = inicial − 20; encadeamento correto | US4-5, SC-002 |

### Configurações

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 31 | `PUT /api/clientes/{id}/configuracoes` mudando `valor_maximo_aposta` e `bloquear_saque: true`; depois mínimo > máximo ou `quantidade_maxima_saques_diaria: 0` | `200`; depois `422` | US6-1, US6-2 |
| 32 | Alterar `clientes_configuracoes_padrao` e cadastrar um cliente | o novo recebe o padrão novo; os antigos não mudam | FR-049 |
| 33 | Vendedor tenta alterar configurações | `403` | US6-4 |

### Gestão de clientes

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 34 | `GET /api/clientes` buscando por nome, parte do telefone, CPF e e-mail; filtros `saque_bloqueado`, `com_cpf=false`, `com_email=true`, faixa de saldo; ordenação `saldo desc` | resultados corretos, paginados (padrão 20) | US7-1, FR-056 |
| 35 | Gerente sem `clientes.ver_dados_completos` busca por parte do CPF e pelo CPF completo | dados mascarados; parte não encontra; completo encontra | US7-2, FR-057 |
| 36 | Desativar; cliente usa o token e tenta entrar; reativar | `403` com o token; login `403`; ao reativar, login volta (token antigo segue recusado) | US7-3 |
| 37 | Gerente com `clientes.excluir` tenta excluir | `403` | US7-7 |
| 38 | Admin exclui o cliente | `204`; telefone, CPF e e-mail com `_deleted_<timestamp>`; token do cliente `401`; login `401` genérico | US7-4, FR-060 |
| 39 | Cadastrar novo cliente com os mesmos telefone, CPF e e-mail | `201` | Edge |
| 40 | Restaurar o excluído sem dados novos; depois com novos e livres | `422` com os campos em conflito; depois `200`, sem reaplicar promoção | US7-6, FR-076 |
| 41 | Excluir e restaurar um cliente sem conflito | volta com os dados originais | US7-5 |
| 42 | Vendedor em `GET /api/clientes` | `403` | FR-055 |

### Promoções e estorno

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 43 | Segunda promoção `"Primeiro cadastro"` + `"Esportes"` com período sobreposto | `422` sobreposição | US8-2 |
| 44 | Promoção `"Primeiro depósito"` + `"Esportes"` no mesmo período | `201` | US8-3 |
| 45 | `"Primeiro cadastro"` com `"Percentual"`; `"Primeiro depósito"` com `rollover: 0`; `"Percentual"` sem `valor_maximo_deposito`; `valor_minimo_aposta` > máximo | `422` em cada caso | US8-4, FR-065 a FR-067 |
| 46 | Editar o `valor` de uma promoção já aplicada; editar o `nome` | `422`; `200` | FR-073 |
| 47 | Com a promoção do passo 6 aplicada a 3 clientes (um deles com `saldo_promocao_esportes` reduzido a `5.00` por débito manual e outro com `0.00`, e um com `50.00` no `saldo` real), `POST /api/clientes_promocoes/{id}/estornar` com motivo | `202`; ao fim do job: saldos promocionais em `0.00`; transações `"Estorno"` de `20.00` e `5.00`; nenhuma para o de saldo `0.00`; `saldo` real intacto; promoção inativa com `estorno.situacao = "Concluído"` e contadores corretos | US10-1 a US10-4, FR-079 |
| 48 | Estornar de novo; Gerente com `clientes_promocoes.estornar` tenta estornar outra | `422`; `403` | US10-5, US10-6 |
| 49 | Parar o `queue:work` durante um estorno grande e religar | o estorno termina sem nenhum cliente com dois `"Estorno"` da mesma promoção | FR-080 |
| 50 | Editar ou reativar a promoção estornada | `422` | FR-068 |

### Carga

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 51 | No `php artisan tinker`: `Clientes::factory()->count(100000)->create()` e 10.000 transações para um cliente via `SaldoClientes`; medir `GET /api/clientes?busca=...` e o extrato desse cliente | menos de 2 s | SC-009 |
| 52 | Promoção aplicada a 10.000 clientes (via tinker); estornar e medir até `"Concluído"` | menos de 10 min | SC-010 |

## 4. Coleção do Postman

Importar `docs/postman/wssports_api.postman_collection.json` e conferir as pastas "Área do
cliente" e "Clientes (painel)", com as variáveis `base_url`, `token` (painel) e `token_cliente`
preenchidas automaticamente pelos logins, cadastros e renovações.
