# Data Model: Painel — Bônus

**Feature**: [spec.md](spec.md) | **Research**: [research.md](research.md)

Valores em reais com 2 casas (`decimal(15,2)`), calculados com bcmath; datas gravadas em UTC e
mostradas no fuso do sistema (Brasília).

## 1. Tabela nova

### 1.1 `clientes_promocoes_estornos` (FR-022, FR-026 a FR-028; R-04)

Um registro por bônus estornado (individual, geral ou automático).

| Coluna | Tipo | Regras | Comentário no banco |
|---|---|---|---|
| `id` | `id()` | | |
| `clientes_promocoes_id` | `foreignId` → `clientes_promocoes` | obrigatório | Promoção do bônus estornado |
| `clientes_id` | `foreignId` → `clientes` | obrigatório | Cliente que recebeu o bônus |
| `clientes_transacoes_id` | `foreignId` → `clientes_transacoes` | obrigatório, **único** | Crédito do bônus (origem Promoção) que foi estornado; único para não estornar duas vezes |
| `clientes_rollovers_id` | `foreignId` → `clientes_rollovers` | nulo | Rollover do bônus cancelado pelo estorno (nulo quando a promoção não exigia rollover) |
| `tipo` | `string(20)` | `TipoEstornoBonus` | Como o bônus foi estornado: Individual, Geral ou Automático |
| `motivo` | `string(255)` | obrigatório | Motivo informado no estorno (no automático, o evento que o causou) |
| `usuarios_id` | `foreignId` → `usuarios` | nulo | Usuário do painel que estornou (nulo no automático) |
| `valor_retirado` | `decimal(15,2)` | ≥ 0 | Valor retirado do saldo promocional da modalidade (R$) |
| `debito_clientes_transacoes_id` | `foreignId` → `clientes_transacoes` | nulo | Transação de débito do estorno (nula quando não havia saldo para retirar) |
| `created_at`, `updated_at`, `deleted_at` | | | |

Índices: único `clientes_transacoes_id`; `(clientes_promocoes_id, tipo)`; `(clientes_id)`.
Model `ClientesPromocoesEstornos` com `SoftDeletes`, casts (`tipo` → `TipoEstornoBonus`,
`valor_retirado` → `decimal:2`) e relações `promocao`, `cliente`, `credito`, `rollover`, `autor`, `debito`.

## 2. Tabelas existentes (sem coluna nova)

| Tabela | Uso nesta spec |
|---|---|
| `clientes_promocoes` | Lista, cadastro e edição; situação calculada; estorno geral (colunas `estorno_*` atuais) |
| `clientes_transacoes` | Bônus recebido = crédito `Promoção` (`referencia_id` = promoção); números de uso pelas origens `Promoção`, `Conversão de bônus`, `Bônus expirado`, `Bônus excluído` e `Estorno` |
| `clientes_rollovers` | Progresso do bônus (`valor_exigido`, `valor_apostado`, `expira_em`, `cumprido_em`, `convertido_em`, `expirado_em`, `excluido_em`, `cancelado_em`) e regras gravadas |
| `clientes`, `afiliados`, `usuarios` | Nome e saldo promocional do cliente; alcance pela rede (afiliado → gerente) |

## 3. Índices novos (R-13)

| Tabela | Índice |
|---|---|
| `clientes_promocoes` | `(afiliados_id, data_inicio)`; `(nome)` |
| `clientes_rollovers` | `(clientes_transacoes_id)` |
| `clientes_transacoes` | `(origem, carteira, created_at)` |

## 4. Enums novos

| Enum | Casos → valores |
|---|---|
| `TipoEstornoBonus` | `Individual` → `'Individual'`, `Geral` → `'Geral'`, `Automático` → `'Automático'` |
| `SituacaoBonus` (não gravado) | `EmAndamento` → `'Em andamento'`, `Cumprido` → `'Cumprido'`, `Convertido` → `'Convertido'`, `Expirado` → `'Expirado'`, `ExcluídoPeloCliente` → `'Excluído pelo cliente'`, `Estornado` → `'Estornado'` |
| `SituacaoPromocao` (não gravado) | `Vigente`, `Agendada`, `Encerrada`, `Desativada`, `Estornando`, `Estornada` (valores iguais ao nome) |

## 5. Valores derivados (não gravados)

| Valor | Regra |
|---|---|
| Situação da promoção | R-07 (`CASE`: Estornando, Estornada, Desativada, Agendada, Encerrada, Vigente) |
| Situação do bônus | R-03 (`CASE`: Estornado, Convertido, Expirado, Excluído pelo cliente, Estornado legado, Cumprido, Em andamento) |
| Números de uso | Clientes (distintos com crédito `Promoção`), Creditado (Σ `Promoção`), Cumpridos (créditos com rollover cumprido ou sem rollover), Convertido (Σ créditos `Conversão de bônus` em `Saldo`), Retirado (Σ débitos `Bônus expirado` + `Bônus excluído` + `Estorno`); por promoção, só clientes da rede para Supervisor/Gerente |
| Falta apostar | `max(valor_exigido − valor_apostado, 0)`; 0 sem rollover |
| Valor a retirar no estorno | `min(saldo da carteira do cliente, valor do crédito)`, nunca negativo |

## 6. Regras de validação

| Campo / ação | Regra | Mensagem |
|---|---|---|
| `nome` | único entre as não excluídas (só cobrado se mudar) | "Já existe uma promoção com este nome." |
| `data_fim` | quando enviada e diferente da gravada: depois de agora | "A data de fim já passou." |
| `afiliados_id` | Supervisor/Gerente: obrigatório e da rede; Admin: opcional | "Afiliado não encontrado." |
| Sobreposição | ativa + mesma categoria + modalidade + mesmo afiliado (ou ambas gerais) + períodos que se cruzam | "Já existe uma promoção ativa desta categoria e modalidade no período: {nome} (ID {id})." |
| Alterar promoção geral | Supervisor/Gerente | "Só o Admin pode alterar promoções gerais." (403) |
| Excluir | promoção aplicada ou estornada | "Promoção já aplicada não pode ser excluída; desative ou estorne." |
| `senha` (estornos) | senha do usuário logado | "Senha incorreta." |
| `motivo` (estornos) | obrigatório, até 255 | "O motivo do estorno é obrigatório." / "O motivo deve ter no máximo 255 caracteres." |
| Estorno individual | bônus Em andamento ou Cumprido | "Este bônus não pode mais ser estornado." |
| Período (bônus recebidos) | `data_inicial ≤ data_final`, até 1 ano | "O período deve ter no máximo 1 ano." |

As demais regras do cadastro continuam as do `ClientesPromocoesRequest` atual.

## 7. Transições de estado

**Promoção**: Agendada → Vigente → Encerrada (pelo tempo); qualquer uma ↔ Desativada (ativar/desativar,
se não estornada); qualquer uma → Estornando → Estornada (estorno geral, sem volta). Excluída só se
nunca aplicada.

**Bônus recebido**: Em andamento → Cumprido (rollover) → Convertido (cliente); Em andamento → Expirado
(prazo) / Excluído pelo cliente; Em andamento ou Cumprido → Estornado (individual, geral ou automático).
Convertido, Expirado, Excluído e Estornado são finais.
