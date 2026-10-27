# Data Model: Painel — Afiliados

**Feature**: `022-painel-afiliados` | **Date**: 2026-10-27

Toda coluna criada tem `->comment()` em português (constituição). Nenhuma tabela nova.

## 1. `afiliados` (alterada — migration `add_perfil_afiliados_table`)

| Coluna | Tipo | Regras |
|---|---|---|
| `rede_social` | varchar(20) nulo | enum `RedeSocialAfiliado`: `Instagram`, `TikTok`, `YouTube`, `Facebook`, `Kwai`, `X`, `Outra`. Obrigatória nos cadastros novos (painel e público) |
| `link_perfil` | varchar(255) nulo | `url:http,https`, até 255. Obrigatório nos cadastros novos |
| `quantidade_seguidores` | int unsigned nulo | inteiro de 0 a 999.999.999. Obrigatório nos cadastros novos |
| `sobre` | text nulo | "Sobre você", até 1.000 caracteres; opcional |

- Nulos no banco: afiliados existentes ficam "Não informado". Depois de preenchidos, `rede_social`,
  `link_perfil` e `quantidade_seguidores` não voltam a vazio (validação na edição e em "Meus
  dados": `required` quando o valor atual não é nulo).
- `$fillable` do model ganha os quatro campos; cast `rede_social` → `RedeSocialAfiliado`,
  `quantidade_seguidores` → `integer`.
- Relações novas no model `Afiliados`: `autor_cadastro()` (`cadastrado_por` → `usuarios`) e
  `autor_decisao()` (`decidido_por` → `usuarios`), `BelongsTo`, com `withTrashed()`.

### Enum novo `App\Enums\RedeSocialAfiliado`

Casos `Instagram`, `TikTok`, `YouTube`, `Facebook`, `Kwai`, `X`, `Outra`; valores iguais ao nome do
caso (Princípio I).

### Ciclo de vida (`situacao`) — mudanças

```text
cadastro pelo painel (regra obrigatória) ─────────────► Ativo ◄──────► Inativo
cadastro público (sem regra) ──► Em análise ──┬─► Ativo (aprovar: só Admin; gerente + regra obrigatórios)
                                              └─► Recusado (recusar: só Admin) ──► Ativo (aprovar depois)
qualquer situação ──► lixeira (excluir: Admin/Supervisor + senha) ──► situação anterior (restaurar)
```

- Afiliado sem regra só existe Em análise ou Recusado (não recebe clientes, não gera lançamentos).

## 2. `afiliados_comissoes` (sem mudança de estrutura)

- A regra inicial passa a ser sempre a informada no cadastro pelo painel (`usuarios_id` = quem
  cadastrou) ou na aprovação (`usuarios_id` = o Admin). O cadastro público não cria linha.
- Na aprovação de afiliado que já tem regra (cadastros anteriores a esta spec), a vigente é
  encerrada e a nova aberta (`RegraComissaoAfiliados::trocar`), mesmo que os valores sejam iguais.

## 3. `configuracoes` (alterada — migration `remove_afiliados_configuracoes_table`)

- Removidas `afiliados_tipo_ganho_padrao` e `afiliados_percentual_padrao` (spec 012, FR-015). O
  `down()` recria as duas com os padrões antigos (`Saldo líquido`, `0`).

## 4. Valores calculados (sem coluna)

| Valor | Onde | Cálculo |
|---|---|---|
| `entradas` | listagem | soma de `afiliados_lancamentos.base` em aberto (`afiliados_prestacoes_contas_id` nulo) |
| `valor_a_receber` | listagem, consulta | soma de `valor` em aberto (já existe) |
| `totais_periodo` | listagem | cadastros, depósitos, saques e comissão do período (já existe; padrão agora "este mês") |
| `total_afiliados` | resumo | contagem dos Ativos e Inativos do filtro (rede, busca, gerente; sem período) |
| `em_analise` | resumo (Admin) | contagem de todos os Em análise, sem busca nem filtros |
| `comissao_periodo` | resumo | soma de `valor` dos lançamentos com `ocorrido_em` no período, dos afiliados do filtro |
| `a_receber` | resumo | soma de `valor` em aberto dos afiliados do filtro |
| `soma` do extrato | gaveta | soma de `valor` dos lançamentos do filtro do extrato |

Todos em `decimal` vindo do banco e formatados com `bcadd(..., '0', 2)` (sem ponto flutuante).

## 5. Índices

Nenhum novo: `afiliados_lancamentos` já tem índice por `afiliados_id` e prestação (spec 012);
`afiliados` por `situacao`, `usuarios_id` e `nome`.
