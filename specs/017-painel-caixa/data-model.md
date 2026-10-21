# Data Model: Painel — Caixa

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

Toda tabela nova tem `timestamps()` e `softDeletes()`, e toda coluna nova tem `->comment()` em
português (constituição). Valores em reais com `decimal(15,2)`; percentuais com `decimal(5,2)`.

## Tabelas novas

### `caixa_prestacoes_contas`

Fechamento do caixa de um vendedor (FR-017 a FR-022a). Criada só por `PrestacaoContasCaixa`; nunca
editada. Desfazer = preencher `desfeita_por`/`motivo_desfazer` e aplicar o soft delete
(`deleted_at` = quando foi desfeita).

| Coluna | Tipo | Regras | Comentário |
|---|---|---|---|
| `id` | id | | |
| `usuarios_id` | foreignId → `usuarios` | obrigatório; função Vendedor | Vendedor cujo caixa foi prestado |
| `periodo_inicio` | dateTime, nullable | fim da prestação anterior não desfeita; null na primeira | Início do período da prestação (fim da prestação anterior); vazio na primeira prestação do vendedor |
| `periodo_fim` | dateTime | > `periodo_inicio`; ≤ agora | Fim do período da prestação: data e hora até onde o caixa foi fechado |
| `saldo_anterior` | decimal(15,2) | pode ser negativo | Diferença deixada pela prestação anterior (apurado − recebido), já incluída no valor apurado |
| `valor_apurado` | decimal(15,2) | pode ser negativo (banca deve ao vendedor) | Saldo de caixa do vendedor no fim do período, calculado na prestação |
| `valor_recebido` | decimal(15,2) | duas casas; pode ser negativo (a banca pagou ao vendedor) | Valor efetivamente recebido do vendedor (negativo quando a banca pagou ao vendedor) |
| `diferenca` | decimal(15,2) | = apurado − recebido | Diferença que fica para a próxima prestação (saldo anterior da próxima) |
| `lote` | char(36), nullable | UUID; igual em todas as prestações do mesmo lote | Identificador da prestação em lote feita pela linha do gerente; vazio na prestação individual |
| `prestado_por` | foreignId → `usuarios` | quem confirmou com a senha | Usuário do painel que fez a prestação de contas |
| `observacao` | string(500), nullable | | Observação opcional da prestação |
| `desfeita_por` | foreignId → `usuarios`, nullable | preenchido só ao desfazer | Usuário que desfez a prestação |
| `motivo_desfazer` | string(500), nullable | obrigatório ao desfazer | Motivo informado ao desfazer a prestação |
| timestamps, softDeletes | | `deleted_at` = desfeita em | |

Índices: `(usuarios_id, periodo_fim)`; `lote`.

Regras: no máximo uma prestação não desfeita com o mesmo `periodo_fim` por vendedor (garantido pelo
bloqueio da linha do vendedor, R-04); só a última não desfeita pode ser desfeita.

### `caixa_lancamentos`

Suprimentos e retiradas no caixa de um vendedor (FR-014 a FR-016).

| Coluna | Tipo | Regras | Comentário |
|---|---|---|---|
| `id` | id | | |
| `usuarios_id` | foreignId → `usuarios` | função Vendedor | Vendedor em cujo caixa o lançamento foi feito |
| `tipo` | string(20) | enum `TipoLancamentoCaixa` | Tipo do lançamento: Suprimento (dinheiro entregue ao vendedor, aumenta o que ele deve) ou Retirada (dinheiro recolhido, diminui o que ele deve) |
| `valor` | decimal(15,2) | > 0 | Valor do lançamento em reais |
| `ocorrido_em` | dateTime | ≤ agora; > fim da última prestação não desfeita | Data e hora em que o dinheiro foi entregue ou recolhido |
| `observacao` | string(500), nullable | | Observação opcional do lançamento |
| `lancado_por` | foreignId → `usuarios` | | Usuário do painel que registrou o lançamento |
| `caixa_prestacoes_contas_id` | foreignId → `caixa_prestacoes_contas`, nullable | preenchido ao prestar; volta a null ao desfazer | Prestação de contas em que o lançamento foi incluído; vazio enquanto está aberto |
| `excluido_por` | foreignId → `usuarios`, nullable | só sem prestação | Usuário que excluiu o lançamento |
| timestamps, softDeletes | | | |

Índices: `(usuarios_id, ocorrido_em)`; `(usuarios_id, caixa_prestacoes_contas_id)`.

## Tabela alterada

### `usuarios_configuracoes`

| Coluna | Tipo | Regras | Comentário |
|---|---|---|---|
| `percentual_comissao_gerente` | decimal(5,2), default 0 | 0 a 100; usado só na linha de um Gerente | Percentual (%) da comissão do gerente sobre o líquido dos vendedores dele no período (zero quando o líquido for ≤ 0) |

## Enum novo

- `TipoLancamentoCaixa`: `Suprimento`, `Retirada`.

## Permissões novas (`Funcao::PERMISSOES_CAIXA`)

| Permissão | Admin | Supervisor | Gerente | Vendedor |
|---|---|---|---|---|
| `caixa.ver` | ✓ | ✓ | ✓ | ✓ (só o próprio) |
| `caixa.ver_clientes` | ✓ | ✓ | — | — |
| `caixa.lancar` | ✓ | ✓ | ✓ | — |
| `caixa.excluir_lancamento` | ✓ | ✓ | ✓ | — |
| `caixa.prestar_contas` | ✓ | ✓ | ✓ | — |
| `caixa.exportar` | ✓ | ✓ | ✓ | — |
| `caixa.editar_comissao_gerente` | ✓ | ✓ | — | — |

"—" = a função não pode usar, mesmo recebendo (`pode_usar`). "Ajustar limite" usa a permissão
existente `usuarios_configuracoes.editar`.

## Entidades calculadas (não gravadas)

### Apuração do caixa (`ApuracaoCaixa`)

Por usuário em foco (banca, supervisor, gerente ou vendedor), intervalo e tipo:

| Campo | Regra |
|---|---|
| `apostas`, `abertas`, `premiadas`, `perdidas` | quantidades das apostas ativas do balcão feitas no intervalo |
| `entradas` | Σ `valor` |
| `em_aberto`, `valor_premiadas`, `valor_perdidas` | Σ `valor` por `resultado` |
| `saidas` | Σ `premio` (Vencedor) + Σ `valor` (Devolvido) |
| `comissao_vendedor` | Σ `comissao` |
| `comissao_gerente` | por gerente: % × (entradas − saídas − comissão do vendedor) dos vendedores dele no intervalo, zero se ≤ 0; somada para cima; nula no Vendedor |
| `lancamentos`, `retiradas` | Σ suprimentos / retiradas não excluídos com `ocorrido_em` no intervalo (só no tipo Todas) |
| `liquido` | entradas − saídas − comissão do vendedor − comissão do gerente |
| `saldo_caixa` | R-03, até agora (só no tipo Todas); no gerente e no supervisor, soma dos vendedores |
| `recebido_periodo` | Σ `valor_recebido` das prestações não desfeitas criadas no intervalo (gerente e supervisor) |
| `limites` | geral, duplo e simples (só nas linhas de vendedor) |

### Resumo da prestação

`periodo_inicio`, `periodo_fim`, `saldo_anterior`, `entradas`, `comissao`, `saidas`, `suprimentos`,
`retiradas`, `ajustes` (resto: estornos e re-apurações), `valor_apurado`, `apostas_em_aberto`
(quantidade e valor). Invariante: saldo_anterior + entradas − comissão − saídas + suprimentos −
retiradas + ajustes = valor_apurado.

### Caixa clientes (`CaixaClientes`)

Esportes (como `CaixaOnline`: `entradas`, `em_aberto`, `premiadas`, `perdidas`, `saidas`,
`encerramentos`, `saldo_liquido`), cassino (`entradas`, `saidas`, `resultado`, `comissao_provedores`,
carteira Saldo), bônus (`concedidos`, `convertidos`), `depositos`, `saques` e `resultado_site`; na
lista por cliente, as mesmas somas por `clientes_id`.

## Ciclo de vida

```text
Lançamento:  aberto ──prestação──▶ prestado ──desfazer prestação──▶ aberto
             aberto ──excluir──▶ excluído (soft delete, excluido_por)

Prestação:   ativa (a última) ──desfazer──▶ desfeita (soft delete, desfeita_por, motivo)
             ativa (não é a última) — não pode ser desfeita
```
