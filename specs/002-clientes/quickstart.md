# Quickstart: validação manual de Clientes

**Feature**: `002-clientes` | **Plano**: [plan.md](plan.md)

Sem testes automatizados (constituição). Validação por requisições HTTP, pela coleção do Postman
regenerada (`docs/postman/wssports_api.postman_collection.json`). Campos e respostas:
[data-model.md](data-model.md) e [contracts/api.md](contracts/api.md).

## Pré-requisitos

- Spec 001 aplicada (usuários, JWT e spatie funcionando; Admin `admin` / `password`).
- Cliente HTTP com `Accept: application/json`.

## 1. Atualizar o banco (sem apagar nada)

```bash
php artisan migrate
php artisan db:seed --class=ClientesSeeder
```

⚠️ Não use `migrate:refresh` nem `migrate:fresh` ([research.md](research.md) R-17).

**Esperado**: 6 tabelas novas (`clientes`, `clientes_transacoes`, `clientes_configuracoes`,
`clientes_configuracoes_padrao`, `clientes_promocoes`, `clientes_codigos_recuperacao`); 1 registro
em `clientes_configuracoes_padrao` com os valores da antiga `travas_gerentes`; 9 permissões novas
no guard `api`, dadas ao Admin; clientes de exemplo; tabelas existentes intactas.

## 2. Conferir as rotas

```bash
php artisan route:list --path=api/area_cliente
php artisan route:list --path=api/clientes
```

**Esperado**: 10 rotas em `area_cliente` e 18 em `clientes`/`clientes_*` (sem `store` em
`clientes`).

## 3. Roteiro

Tokens: `token_admin` (login do painel com `admin`), `token_cliente` (login da área do cliente).
Para os testes de permissão, dê ou tire permissões pelas rotas da spec 001.

| # | Ação | Esperado | Ref. |
|---|---|---|---|
| 1 | `POST /api/area_cliente/cadastro` com dados válidos, sem promoção ativa | `201`; cliente ativo, 3 saldos `0.00`; configurações iguais ao padrão; linha no log "WhatsApp" de boas-vindas | US1-1, FR-011, FR-013 |
| 2 | Repetir o cadastro com o mesmo telefone (com máscara `(11) 98888-7777`) | `422` telefone já cadastrado | US1-3, Edge |
| 3 | Cadastro com CPF repetido; CPF inválido; nascido há 17 anos; senha `abcdefgh`; confirmação diferente | `422` com a mensagem de cada caso | US1-4 a US1-7 |
| 4 | Cadastro sem `codigo_pais` | cliente com `codigo_pais = "55"` | US1-8 |
| 5 | Criar promoção de primeiro cadastro de `20.00` em esportes (`POST /api/clientes_promocoes`, `token_admin`) e cadastrar um cliente | `saldo_promocao_esportes = "20.00"` e transação de origem `promocao` com `referencia_id` da promoção | US1-2, US8, FR-064 |
| 6 | Cadastrar outro cliente com `aceita_promocao: false` | saldos promocionais `0.00`, sem transação | US1-11 |
| 7 | Criar segunda promoção de primeiro cadastro em esportes com período sobreposto | `422` sobreposição | US8-2 |
| 8 | `POST /api/area_cliente/auth/login` com telefone e senha certos | `200` com token | US2-1 |
| 9 | Senha errada; 6 tentativas erradas em 1 min | `401` genérico; depois `429` com o tempo de espera | US2-2, US2-7 |
| 10 | Usar `token_cliente` em `GET /api/usuarios` e `token_admin` em `GET /api/area_cliente/meus_dados` | `401` nos dois | US2-4, SC-004 |
| 11 | `refresh` e `logout` do cliente; reutilizar o token após logout | novo token; `204`; depois `401` | US2-5, US2-6 |
| 12 | `GET /api/area_cliente/meus_dados` | dados e 3 saldos, sem `password` | US5-1 |
| 13 | `PATCH meus_dados` com `nome`; depois com `cpf` | `200`; depois `422` | US5-2, US5-3 |
| 14 | `PUT meus_dados/senha` com senha atual errada; depois certa | `422`; depois `204` e o token antigo passa a `401` | US5-4, R-02 |
| 15 | `POST /api/clientes/{id}/transacoes` crédito `100.00` em `saldo`, depois débito `30.00` | saldo `70.00`; 2 transações com anterior/posterior `0.00→100.00` e `100.00→70.00`, autor = Admin | US4-1, US4-2 |
| 16 | Débito de `100.00` | `422 "Saldo insuficiente."`, saldo continua `70.00`, sem transação | US4-3 |
| 17 | Crédito em `saldo_promocao_cassino` | só essa carteira muda | US4-4 |
| 18 | Valor `0`, `-5`, `10.555` ou sem `observacao` | `422` | US4-7 |
| 19 | Disparar 2 débitos de `10.00` ao mesmo tempo (duas abas do Postman / `curl &`) | saldo final = inicial − 20; o anterior de uma = posterior da outra | US4-5, SC-002 |
| 20 | `GET /api/area_cliente/meus_dados/extrato?data_inicial=...&data_final=...` | só o período, mais recente primeiro; sem `autor`; data inicial > final → `422` | US5-5, US5-6 |
| 21 | `PUT /api/clientes/{id}/configuracoes` mudando `valor_maximo_aposta`; depois mínimo > máximo | `200`; depois `422` | US6-1, US6-2 |
| 22 | Alterar `clientes_configuracoes_padrao` e cadastrar um cliente | o novo cliente recebe o padrão novo; os antigos não mudam | FR-043 |
| 23 | `GET /api/clientes?busca=...` por nome, por parte do telefone e por CPF; filtros `ativo`, `genero`, faixa de saldo, `com_saldo_promocional`, ordenação por `saldo desc` | resultados corretos, paginados (padrão 20) | US7-1, FR-052 |
| 24 | Gerente com `clientes.listar` e sem `clientes.ver_dados_completos`: listar e buscar por parte do CPF e pelo CPF completo | CPF/telefone mascarados; parte do CPF não encontra; CPF completo encontra | US7-2, FR-052a |
| 25 | `PATCH /api/clientes/{id}/situacao` `ativo: false`; cliente tenta usar o token e entrar | `403` com o token; login `403`; ao reativar, o login volta a funcionar (o token antigo continua recusado) | US7-3, US2-8 |
| 26 | Gerente com `clientes.excluir` tenta `DELETE /api/clientes/{id}` | `403` | US7-7, FR-049 |
| 27 | Admin exclui o cliente | `204`; some da listagem; no banco `telefone`/`cpf` com `_deleted_<timestamp>`; saldos e transações preservados; o token que o cliente tinha passa a `401` e o login com o telefone antigo dá `401` genérico | US7-4, FR-055 |
| 28 | Cadastrar novo cliente com o mesmo telefone e CPF do excluído | `201` (dados liberados) | Edge |
| 29 | `POST /api/clientes/{id}/restaurar` sem dados novos | `422` indicando `telefone` e `cpf` em conflito | US7-6 |
| 30 | Restaurar informando `telefone` e `cpf` novos e livres | `200`; cliente volta com os dados novos; a promoção de primeiro cadastro não é reaplicada | US7-6, FR-065 |
| 31 | Excluir e restaurar um cliente sem conflito | volta com telefone/CPF originais | US7-5 |
| 32 | `POST /api/area_cliente/auth/recuperar_senha` com telefone cadastrado e com não cadastrado | mesma resposta `200`; código de 6 dígitos só no log do primeiro | US3-1, US3-2 |
| 33 | Pedir de novo em menos de 1 min | `429` | US3-6 |
| 34 | `redefinir_senha` com código errado 5 vezes; depois com o código certo | `422`; o código foi invalidado, pedir outro | US3-5 |
| 35 | Novo pedido; `redefinir_senha` com o código do log e nova senha | `204`; tokens antigos `401`; login só com a nova senha; reusar o código → `422` | US3-3, US3-4 |
| 36 | Vendedor (qualquer permissão) em `GET /api/clientes` | `403` | FR-049 |
| 37 | Criar promoção de `primeiro_deposito` em esportes com o mesmo período da de `primeiro_cadastro` | `201` (categorias diferentes podem se sobrepor) | US8-3, FR-063 |
| 38 | Carga: no `php artisan tinker`, `Clientes::factory()->count(100000)->create()` e 10.000 transações para um cliente via `SaldoClientes`; medir `GET /api/clientes?busca=...` e o extrato desse cliente | resposta em menos de 2 s | SC-009 |

## 4. Coleção do Postman

Importar `docs/postman/wssports_api.postman_collection.json` e conferir as pastas "Área do
cliente" e "Clientes (painel)", com as variáveis `base_url`, `token` (painel) e `token_cliente`
preenchidas automaticamente pelos logins e renovações.
