# Data Model: Painel — Clientes

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

Esta spec cria **2 tabelas**, acrescenta **3 colunas** e **índices**, e uma migration de dados de
permissões. Toda coluna nova tem `->comment()` em português; toda tabela nova tem `timestamps()` e
`softDeletes()` (constituição).

## 1. Tabelas novas

### 1.1 `clientes_antecipacoes` (FR-019)

Registro de cada antecipação de pendentes.

| Coluna | Tipo | Regra / comentário |
|---|---|---|
| `id` | bigint PK | |
| `clientes_id` | FK `clientes` | Cliente que recebeu a antecipação |
| `usuarios_id` | FK `usuarios` | Usuário do painel que antecipou |
| `quantidade_premios` | unsigned int | Quantidade de apostas com prêmio (ou devolução) creditado na antecipação |
| `valor_premios` | decimal(15,2) | Soma creditada das apostas, em reais |
| `quantidade_encerramentos` | unsigned int | Quantidade de encerramentos (cash out) creditados na antecipação |
| `valor_encerramentos` | decimal(15,2) | Soma creditada dos encerramentos, em reais |
| `ip` | varchar(45) nulo | IP de quem antecipou |
| `created_at`, `updated_at`, `deleted_at` | | |

Índice: `(clientes_id, created_at)`.

### 1.2 `clientes_notificacoes_envios` (FR-031 a FR-033a)

Cada envio manual de notificação push.

| Coluna | Tipo | Regra / comentário |
|---|---|---|
| `id` | bigint PK | |
| `usuarios_id` | FK `usuarios` | Usuário do painel que enviou |
| `publico` | varchar(20) | Público do envio: `Todos`, `Por ID` ou `Sem interagir` (enum `PublicoNotificacao`) |
| `clientes_ids` | json nulo | Ids escolhidos no público `Por ID` |
| `dias_sem_interagir` | unsigned smallint nulo | N dias no público `Sem interagir` (1 a 365) |
| `titulo` | varchar(50) | Título mostrado na notificação |
| `mensagem` | varchar(150) | Texto mostrado na notificação |
| `alcancados` | unsigned int | Clientes inscritos no público no momento do envio |
| `entregues` | unsigned int default 0 | Aparelhos que receberam (somado pelo job) |
| `falhas` | unsigned int default 0 | Aparelhos que recusaram (somado pelo job) |
| `concluido_em` | timestamp nulo | Quando o job terminou de enviar |
| `created_at`, `updated_at`, `deleted_at` | | |

Índice: `(created_at)` (contagem do limite do dia).

## 2. Colunas novas em tabelas existentes

| Tabela | Coluna | Tipo | Comentário |
|---|---|---|---|
| `clientes_inscricoes_notificacoes` | `receber_avisos_banca` | boolean default `true` | Se o aparelho recebe os avisos manuais da banca (tipo Aviso da banca) |
| `clientes_notificacoes` | `clientes_notificacoes_envios_id` | FK nula `clientes_notificacoes_envios` | Envio manual que gerou o aviso; nulo nos avisos automáticos |
| `configuracoes` | `limite_notificacoes_manuais_dia` | unsigned tinyint default 3 | Máximo de envios manuais de notificação push por dia, para a banca toda (1 a 50) |

## 3. Índices novos (R-14)

Criados só se ainda não existirem (`Schema::hasIndex`):

| Tabela | Índice | Uso |
|---|---|---|
| `depositos` | `(clientes_id, situacao, pago_em)` | Saldo líquido, filtro de depósito, ranking |
| `saques` | `(clientes_id, situacao, pago_em)` | Saldo líquido, ranking, filtros da aba Saques |
| `clientes_transacoes` | `(clientes_id, origem, carteira)` | Ajustes manuais no saldo líquido |
| `clientes` | `(afiliados_id)` | Alcance por rede |
| `afiliados` | `(usuarios_id)` | Alcance por rede |

## 4. Enums

| Enum | Mudança |
|---|---|
| `TipoNotificacao` | Caso novo `AvisoBanca = 'Aviso da banca'` → `receber_avisos_banca`; não é de jogo |
| `OrigemApuracao` | Caso novo `Antecipação = 'Antecipação de pendentes'` |
| `PublicoNotificacao` (novo) | `Todos = 'Todos'`, `PorId = 'Por ID'`, `SemInteragir = 'Sem interagir'` |

## 5. Tabelas lidas (sem mudança de estrutura)

| Tabela | Uso |
|---|---|
| `clientes`, `clientes_configuracoes`, `clientes_meios_pagamento`, `clientes_transacoes` | Lista, detalhe, extrato, saldo líquido |
| `afiliados`, `usuarios` | Alcance por rede (gerente responsável e sub-hierarquia) |
| `depositos`, `saques` | Saldo líquido, filtros, ranking, aba Saques |
| `apostas`, `apostas_encerramentos` | Modalidade no período, pendentes, valor apostado, resultado |
| `cassinos_rodadas` | Modalidade cassino, valor apostado, resultado, "sem interagir" |
| `clientes_inscricoes_notificacoes`, `clientes_notificacoes` | Público e envio das notificações |

## 6. Valores derivados (não gravados)

| Valor | Regra |
|---|---|
| Saldo líquido | `SUM(depósitos Aprovado/Estornado: valor − valor_estornado)` + créditos `Ajuste manual` em `Saldo` − saques `Pago` − débitos `Ajuste manual` em `Saldo`, desde o cadastro (FR-006) |
| Rede do cliente | `clientes.afiliados_id → afiliados.usuarios_id` (gerente) → sub-hierarquia do Supervisor |
| Pendentes | Apostas do cliente com `liquidacao_pendente` e diferença positiva entre devido e pago; encerramentos `Registrado` com `creditar_em` futuro |
| Posição no ranking | `(página − 1) × por_página + índice + 1` |
| Envios restantes hoje | `limite_notificacoes_manuais_dia − envios com alcancados > 0 desde 00:00 de hoje em Brasília` |

## 7. Regras de validação

| Campo | Regra |
|---|---|
| Ajuste de saldo `senha` | Obrigatória; a senha do usuário logado, senão "Senha incorreta." |
| Antecipação `senha` | Igual |
| `afiliados_id` do cliente (Supervisor e Gerente) | Obrigatório; afiliado da rede, senão "Escolha um afiliado da sua rede." |
| `afiliados.usuarios_id` (Admin e Supervisor) | Obrigatório no cadastro, na edição e na aprovação; gerente da rede do Supervisor |
| Notificação `titulo` / `mensagem` | Obrigatórios, até 50 / 150 caracteres |
| Notificação `clientes_ids` | 1 a 500 ids, no alcance |
| Notificação `dias` | Inteiro de 1 a 365 |
| `limite_notificacoes_manuais_dia` | Inteiro de 1 a 50; só Admin |
| Filtros com período (`modalidade`, `aniversariantes`) | `periodo_de` e `periodo_ate` obrigatórios, `ate` ≥ `de`, até 366 dias |

## 8. Transições de estado

- **Saque**: sem mudança (spec 007): `Pendente → Processando → Pago`, `Processando → Pendente`
  (falha), `Pendente → Recusado`. Decididos não têm ações (FR-026).
- **Aposta pendente**: `liquidacao_pendente = true → false` na antecipação (como na rotina da janela).
- **Encerramento**: `Registrado → Creditado` na antecipação (como na rotina).
- **Afiliado**: `Em análise → Ativo` só com gerente responsável (FR-003d).
