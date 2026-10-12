# Data Model: Painel do cliente online com depósito e saque

**Feature**: `007-painel-cliente-pagamentos` | **Data**: 2026-10-12

Todas as tabelas novas têm `created_at`, `updated_at` e `deleted_at` (soft delete) e o model usa
`SoftDeletes` (constituição). Dinheiro em `decimal(15,2)`, calculado com `bcmath`/centavos. Datas em
UTC; "dia" do cliente em `America/Sao_Paulo`.

## 1. `depositos` (novo)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `codigo` | char(36) | UUID; único; `external_reference` e chave de idempotência no Mercado Pago |
| `clientes_id` | bigint | FK `clientes` |
| `meio` | varchar(30) | enum `MeioDeposito`: `Pix`, `Cartão de crédito` |
| `valor` | decimal(15,2) | valor creditado no saldo (sem juros) |
| `parcelas` | unsignedTinyInteger nulo | só cartão |
| `valor_total_pago` | decimal(15,2) nulo | total cobrado do cliente com juros (cartão) |
| `comissao` | decimal(15,2) | `application_fee` enviado |
| `percentual_comissao` | decimal(5,2) | percentual usado (histórico) |
| `aceita_bonus` | boolean | escolha do cliente neste depósito |
| `situacao` | varchar(20) | enum `SituacaoDeposito` (transições abaixo) |
| `pagamento_id` | varchar(40) nulo | id do pagamento no Mercado Pago; único |
| `status_detalhe` | varchar(80) nulo | `status_detail` do Mercado Pago (motivo da recusa) |
| `pix_copia_cola` | text nulo | só Pix |
| `pix_qr_code` | mediumText nulo | imagem base64 (só Pix) |
| `expira_em` | timestamp nulo | prazo do Pix |
| `pago_em` | timestamp nulo | `date_approved` |
| `valor_estornado` | decimal(15,2) | padrão 0 |
| `estornado_em` | timestamp nulo | |
| `clientes_transacoes_id` | bigint nulo | FK: crédito do depósito |
| `clientes_promocoes_id` | bigint nulo | FK: promoção aplicada |
| `bonus_clientes_rollovers_id` | bigint nulo | FK `clientes_rollovers`: rollover do bônus recebido |
| `rollover_clientes_rollovers_id` | bigint nulo | FK `clientes_rollovers`: rollover do depósito |
| `usuarios_id` | bigint nulo | FK `usuarios`: autor no acesso assistido |
| `ip` | varchar(45) nulo | |

Índices: `(clientes_id, created_at)`, `(situacao, created_at)`, `pagamento_id` único, `codigo` único.

**Transições** (`SituacaoDeposito`):

- `Pendente` → `Aprovado` | `Recusado` | `Expirado` | `Cancelado`
- `Em análise` → `Aprovado` | `Recusado` | `Cancelado`
- `Pendente` → `Em análise` (cartão `in_process`)
- `Aprovado` → `Estornado` (total) — estorno parcial mantém `Aprovado` e soma `valor_estornado`
- `Recusado`, `Expirado`, `Cancelado`, `Estornado` são finais.

## 2. `saques` (novo)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `codigo` | char(36) | UUID; único; chave de idempotência do Payouts |
| `clientes_id` | bigint | FK `clientes` |
| `valor` | decimal(15,2) | |
| `pix_tipo_chave` | varchar(20) | cópia da chave principal no momento (enum `TipoChavePix`) |
| `pix_chave` | varchar(150) | |
| `pix_nome_titular` | varchar(150) | |
| `modo` | varchar(20) | enum `ModoSaque`: `Manual`, `Automático` |
| `situacao` | varchar(20) | enum `SituacaoSaque`: `Pendente`, `Processando`, `Pago`, `Recusado` |
| `motivo_recusa` | varchar(255) nulo | |
| `motivo_falha` | varchar(255) nulo | falha do pagamento automático (fica na fila) |
| `pagamento_id` | varchar(60) nulo | id no Payouts |
| `clientes_transacoes_id` | bigint | FK: débito do saque |
| `devolucao_clientes_transacoes_id` | bigint nulo | FK: devolução |
| `decidido_por` | bigint nulo | FK `usuarios` |
| `decidido_em` | timestamp nulo | |
| `pago_em` | timestamp nulo | |
| `usuarios_id` | bigint nulo | FK `usuarios`: autor no acesso assistido |
| `ip` | varchar(45) nulo | |

Índices: `(clientes_id, created_at)`, `(situacao, created_at)`.

**Transições**: `Pendente` → `Processando` (envio automático) → `Pago`; `Processando` → `Pendente`
(falha, com `motivo_falha`); `Pendente` → `Pago` (baixa manual) | `Recusado` (com devolução).
`Pago` e `Recusado` são finais.

## 3. `gateways_pagamento` (novo; uma linha)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `gateway` | varchar(30) | `Mercado Pago` |
| `situacao` | varchar(20) | enum `SituacaoGateway`: `Vinculado`, `Falha na renovação`, `Desvinculado` |
| `access_token` | text | cast `encrypted` |
| `refresh_token` | text | cast `encrypted` |
| `conta_id` | varchar(40) | `user_id` da conta vinculada |
| `expira_em` | timestamp | validade do access token |
| `vinculado_em` | timestamp | |
| `vinculado_por` | bigint | FK `usuarios` |
| `renovado_em` | timestamp nulo | |
| `falha_renovacao` | varchar(255) nulo | |

Os tokens nunca entram em resource nem log.

## 4. `notificacoes_mercado_pago` (novo)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `tipo` | varchar(40) | `type`/`topic` (`payment`, `chargebacks`, `payouts`...) |
| `recurso_id` | varchar(60) | `data.id` |
| `request_id` | varchar(80) nulo | `x-request-id` |
| `assinatura_valida` | boolean | |
| `situacao` | varchar(20) | `Recebida`, `Processada`, `Ignorada`, `Falhou` |
| `motivo` | varchar(255) nulo | por que foi ignorada ou falhou |
| `processada_em` | timestamp nulo | |

Índice: `(tipo, recurso_id)`.

## 5. `clientes_acessos_assistidos` (novo)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `clientes_id` | bigint | FK `clientes` |
| `usuarios_id` | bigint | FK `usuarios` (admin ou supervisor) |
| `codigo_hash` | char(64) | SHA-256 do código de uso único; único |
| `codigo_expira_em` | timestamp | 2 min |
| `iniciado_em` | timestamp nulo | troca do código |
| `expira_em` | timestamp nulo | iniciado + `minutos_acesso_assistido` |
| `encerrado_em` | timestamp nulo | |
| `ip` | varchar(45) nulo | |

## 6. `clientes_indicacoes` (novo)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `indicador_clientes_id` | bigint | FK `clientes` |
| `indicado_clientes_id` | bigint | FK `clientes`; único |
| `validada_em` | timestamp nulo | primeiro depósito aprovado; nulo = pendente |
| `depositos_id` | bigint nulo | FK: depósito que validou |
| `clientes_indicacoes_bonus_id` | bigint nulo | FK: grupo em que contou |

Índice: `(indicador_clientes_id, validada_em, clientes_indicacoes_bonus_id)`.

## 7. `clientes_indicacoes_bonus` (novo; um por grupo pago)

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | PK |
| `clientes_id` | bigint | FK `clientes` (indicador) |
| `clientes_promocoes_id` | bigint | FK `clientes_promocoes` |
| `quantidade` | unsignedSmallInteger | indicações do grupo |
| `clientes_transacoes_id` | bigint | FK: crédito do bônus |
| `clientes_rollovers_id` | bigint nulo | FK: rollover do bônus |
| `cancelado_em` | timestamp nulo | estorno de um indicado do grupo |

## 8. Alterações em tabelas existentes

### `clientes` (spec 002)

| Coluna | Tipo | Regra |
|---|---|---|
| `codigo_indicacao` | varchar(8) | único; gerado no cadastro; migration preenche os existentes |
| `indicado_por` | bigint nulo | FK `clientes` |

### `clientes_promocoes` (spec 002)

| Coluna | Tipo | Regra |
|---|---|---|
| `dias_cumprir` | unsignedSmallInteger | padrão 30; mínimo 1 |
| `rollover_primeiro_deposito` | boolean | padrão `false`; só `true` em Primeiro depósito |
| `codigo_afiliado` | varchar(50) nulo | sem FK (tabela de afiliados não existe; ajustar na spec de afiliados) |
| `quantidade_indicacoes` | unsignedSmallInteger nulo | obrigatório em Indicação (padrão 5); nulo nas demais |

### `clientes_rollovers` (spec 004)

| Coluna | Tipo | Regra |
|---|---|---|
| `expira_em` | timestamp nulo | só Bônus; migration preenche `created_at + 30 dias` nos pendentes |
| `expirado_em` | timestamp nulo | retirado por expiração |
| `excluido_em` | timestamp nulo | excluído pelo cliente |
| `convertido_em` | timestamp nulo | convertido para o saldo |

`scopePendentes` passa a excluir também `expirado_em` e `excluido_em` não nulos.

### `configuracoes`

| Coluna | Tipo | Padrão |
|---|---|---|
| `gateway_pagamento` | varchar(30) nulo | nulo (sem gateway) |
| `percentual_comissao_gateway` | decimal(5,2) | 3.00 (fora de fillable/resource) |
| `depositos_habilitados` | boolean | true |
| `saques_habilitados` | boolean | true |
| `deposito_minimo` | decimal(15,2) | 1.00 |
| `deposito_maximo` | decimal(15,2) | 50000.00 |
| `saque_minimo` | decimal(15,2) | 10.00 |
| `rollover_deposito` | boolean | true |
| `rollover_deposito_vezes` | unsignedTinyInteger | 1 |
| `cashback_percentual` | decimal(5,2) | 0.00 |
| `modo_saque` | varchar(20) | `Manual` |
| `saque_automatico_maximo` | decimal(15,2) | 1000.00 |
| `meios_deposito` | json | `["Pix","Cartão de crédito"]` |
| `parcelas_maximas` | unsignedTinyInteger | 12 (1 a 12) |
| `minutos_expiracao_pix` | unsignedSmallInteger | 30 |
| `minutos_acesso_assistido` | unsignedSmallInteger | 60 |

### `clientes_configuracoes` (spec 002)

Sem colunas novas: `aceita_promocao` guarda a escolha "Aceito receber bônus" (FR-021);
`bloquear_saque`, `valor_maximo_saque_diario` e `quantidade_maxima_saques_diaria` são os limites
do FR-035.

## 9. Enums

| Enum | Casos (valor) |
|---|---|
| `MeioDeposito` (novo) | `Pix`, `CartaoCredito` (`Cartão de crédito`) |
| `SituacaoDeposito` (novo) | `Pendente`, `EmAnalise` (`Em análise`), `Aprovado`, `Recusado`, `Expirado`, `Cancelado`, `Estornado` |
| `SituacaoSaque` (novo) | `Pendente`, `Processando`, `Pago`, `Recusado` |
| `ModoSaque` (novo) | `Manual`, `Automático` |
| `SituacaoGateway` (novo) | `Vinculado`, `FalhaRenovacao` (`Falha na renovação`), `Desvinculado` |
| `OrigemTransacao` (existente) | + `Depósito`, `Cashback`, `Saque`, `DevolucaoSaque` (`Devolução de saque`), `EstornoDeposito` (`Estorno de depósito`), `ConversaoBonus` (`Conversão de bônus`), `BonusExpirado` (`Bônus expirado`), `BonusExcluido` (`Bônus excluído`) |

## 10. Permissões (`Funcao.php`)

| Permissão | Quem pode usar |
|---|---|
| `clientes.acessar_conta` | Admin, Supervisor |
| `saques.gerenciar` | Admin, Supervisor |
| `gateway_pagamento.vincular` | só Admin |
| `configuracoes.editar` (existente) | Admin, Supervisor — passa a cobrir as configurações de pagamento |

## 11. Estado no navegador

| Chave | Onde | Conteúdo |
|---|---|---|
| `wssports.acesso_assistido` | `sessionStorage` | token assistido, nome do cliente e expiração |
| sessão do cliente (existente) | `localStorage` | sem mudança |
