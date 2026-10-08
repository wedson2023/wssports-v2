# Data Model: Apostas (criação, validação de código e cancelamento)

**Feature**: `004-apostas` | **Data**: 2026-10-07 | **Plano**: [plan.md](plan.md)

5 tabelas novas e 6 tabelas alteradas (colunas novas com valor padrão). Todas as tabelas novas têm
`id`, `created_at`, `updated_at` e `deleted_at` (constituição); datas em UTC. Dinheiro em
`decimal(15,2)`, cotações em `decimal(8,2)` e cotação total em `decimal(14,2)`. Decisões em
[research.md](research.md).

## Tabelas novas

### Tabela `apostas`

| Coluna | Tipo | Regras |
|---|---|---|
| codigo | char(8) | único; alfabeto sem 0, O, 1, I (R-09) |
| chave_idempotencia | char(36) | única; UUID enviado pelo front (R-08) |
| nome | varchar(100) | nome do apostador, sem HTML |
| situacao | varchar(20) | `SituacaoAposta` |
| resultado | varchar(20) | `ResultadoAposta`; padrão `Aguardando` |
| tipo | varchar(20) | `TipoAposta`: `Pré-jogo` ou `Ao vivo` (aposta mista = Ao vivo) |
| forma_pagamento | varchar(30) null | `FormaPagamento`: `Dinheiro`, `Saldo`, `Promoção esportes`; nulo na Pendente |
| aceitar_alteracoes | varchar(20) | `AceitarAlteracoes`; padrão `Nenhuma` |
| valor | decimal(15,2) | > 0 |
| cotacao_total | decimal(14,2) | produto das cotações dos palpites ativos (R-06) |
| premio | decimal(15,2) | FR-025 b |
| valor_acrescido | decimal(15,2) | padrão 0; FR-025 c/d |
| comissao | decimal(15,2) | padrão 0; só vendedor |
| percentual_comissao | decimal(5,2) | padrão 0; percentual usado |
| comissao_por_premio | decimal(5,2) | padrão 0; percentual usado no prêmio líquido |
| multiplicador | unsigned int | usado no cálculo |
| premio_maximo | decimal(15,2) | usado no cálculo |
| ganho_multiplo_palpites | decimal(5,2) | usado no cálculo |
| tempo_cancelamento_aposta | unsigned smallint null | minutos; só aposta de vendedor |
| usuarios_id | FK `usuarios` null | vendedor (aposta de vendedor ou validada) |
| clientes_id | FK `clientes` null | cliente da aposta |
| recebida_em | timestamp | relógio do servidor no envio |
| validada_em | timestamp null | validação do código |
| decidida_em | timestamp null | decisão do ao vivo |
| confirmada_em | timestamp null | quando virou Ativa (base do tempo de cancelamento) |
| expira_em | timestamp null | só Pendente: `recebida_em + horas_validade_codigo` |
| cancelada_em | timestamp null | |
| cancelada_por | FK `usuarios` null | autor do cancelamento |
| motivo_recusa | varchar(255) null | Recusada ou Expirada |
| assinatura | char(64) null | HMAC-SHA256 em hexadecimal (R-11); nula na Pendente |
| ip_criacao, ip_validacao, ip_cancelamento | varchar(45) null | |
| user_agent_criacao, user_agent_validacao, user_agent_cancelamento | varchar(255) null | |

**Índices**: único (`codigo`); único (`chave_idempotencia`); (`usuarios_id`, `situacao`,
`created_at`); (`clientes_id`, `situacao`, `created_at`); (`situacao`, `expira_em`);
(`situacao`, `recebida_em`).

**Regras**: no máximo um entre vendedor e cliente; a aposta de visitante Pendente não tem nenhum
dos dois; `usuarios_id` é preenchido na validação. Transições em [research.md](research.md) R-12.

### Tabela `apostas_palpites`

| Coluna | Tipo | Regras |
|---|---|---|
| apostas_id | FK `apostas` | |
| confrontos_id | FK `confrontos` | sempre preenchido (o ao vivo aponta para o confronto da grade) |
| confrontos_ao_vivo_id | FK `confrontos_ao_vivo` null | só palpite do ao vivo |
| campeonatos_id | FK `campeonatos` | para o comprovante e regras |
| esporte | varchar(50) | do confronto |
| codigo_cotacao | varchar(10) | `odd1`…`odd323` ou `jogador` |
| confrontos_jogadores_id | FK `confrontos_jogadores` null | palpite em jogador |
| jogador_tipo | varchar(60) null | copiado de `confrontos_jogadores.tipo` (nunca do front) |
| cotacao_vista | decimal(8,2) | enviada pelo apostador |
| cotacao_original | decimal(8,2) | do provedor no momento |
| cotacao_final | decimal(8,2) | usada no prêmio |
| dados_ao_vivo_envio | json null | placar, gols por tempo, escanteios, minuto, situação, última atualização (R-02) |
| dados_ao_vivo_decisao | json null | idem, na decisão |
| situacao | varchar(20) | `SituacaoPalpite`: `Ativo` (padrão) ou `Cancelado` |
| cancelado_em, restaurado_em | timestamp null | |
| cancelado_por, restaurado_por | FK `usuarios` null | |

**Índices**: único (`apostas_id`, `confrontos_id`) (FR-015); (`confrontos_id`);
(`confrontos_ao_vivo_id`).

### Tabela `apostas_historico`

| Coluna | Tipo | Regras |
|---|---|---|
| apostas_id | FK `apostas` | |
| apostas_palpites_id | FK `apostas_palpites` | |
| acao | varchar(30) | `AcaoHistoricoAposta`: `Cancelar palpite`, `Restaurar palpite` |
| cotacao_total_anterior, cotacao_total_posterior | decimal(14,2) | |
| premio_anterior, premio_posterior | decimal(15,2) | |
| valor_acrescido_anterior, valor_acrescido_posterior | decimal(15,2) | |
| usuarios_id | FK `usuarios` | autor |
| ip | varchar(45) | |
| user_agent | varchar(255) null | |

**Índice**: (`apostas_id`, `created_at`).

### Tabela `clientes_rollovers`

| Coluna | Tipo | Regras |
|---|---|---|
| clientes_id | FK `clientes` | |
| tipo | varchar(20) | `TipoRollover`: `Depósito` ou `Bônus` |
| carteira | varchar(30) | `Carteira` (Saldo para Depósito; Promoção esportes/cassino para Bônus) |
| clientes_transacoes_id | FK `clientes_transacoes` | crédito que gerou o rollover |
| clientes_promocoes_id | FK `clientes_promocoes` null | só Bônus |
| valor_creditado | decimal(15,2) | > 0 |
| vezes | unsigned smallint | ≥ 1 (Depósito = 1) |
| valor_exigido | decimal(15,2) | creditado × vezes |
| valor_apostado | decimal(15,2) | padrão 0; nunca passa do exigido |
| valor_minimo_aposta, valor_maximo_aposta | decimal(15,2) null | regras do bônus gravadas |
| odd_minima_aposta_simples, odd_minima_aposta_multipla | decimal(8,2) null | idem |
| cumprido_em | timestamp null | quando `valor_apostado = valor_exigido` |
| cancelado_em | timestamp null | promoção estornada (R-13) |

**Índice**: (`clientes_id`, `tipo`, `carteira`, `cumprido_em`, `cancelado_em`).
**Pendente** = `cumprido_em` e `cancelado_em` nulos.

### Tabela `apostas_rollovers`

| Coluna | Tipo | Regras |
|---|---|---|
| apostas_id | FK `apostas` | |
| clientes_rollovers_id | FK `clientes_rollovers` | |
| valor | decimal(15,2) | quanto a aposta somou nesse rollover |
| desfeito_em | timestamp null | preenchido no cancelamento da aposta |

**Índice**: único (`apostas_id`, `clientes_rollovers_id`).

## Tabelas alteradas

### `usuarios_configuracoes` (só vendedor; FR-059)

| Coluna nova | Tipo | Padrão |
|---|---|---|
| realizar_aposta | boolean | `true` |
| cancelar_aposta | boolean | `true` |
| tempo_cancelamento_aposta | unsigned smallint | 5 (minutos) |
| apostar_jogadores | boolean | `true` |
| periodo_jogos | varchar(20) | `Depois de amanhã` |
| data_travamento_sistema | dateTime null | sem trava |
| mensagem_bilhete | varchar(500) | `BOA SORTE!` |
| delay_ao_vivo | unsigned smallint | 15 (segundos) |
| quantidade_minima_opcoes / quantidade_maxima_opcoes | unsigned smallint | 1 / 20 |
| valor_minimo_aposta / valor_maximo_aposta | decimal(15,2) | 2.00 / 1000.00 |
| odd_minima | decimal(8,2) | 1.00 |
| premio_maximo | decimal(15,2) | 5000.00 |
| multiplicador | unsigned int | 1000 |
| ganho_multiplo_palpites | decimal(5,2) | 0 |
| comissao_pre_jogo_1 … comissao_pre_jogo_12 | decimal(5,2) | 0 |
| comissao_ao_vivo_1 … comissao_ao_vivo_12 | decimal(5,2) | 0 |
| comissao_por_premio | decimal(5,2) | 0 |
| limite_simples / limite_duplo / limite_geral | decimal(15,2) | 5000.00 cada (saldo disponível de vendas) |

`UsuariosConfiguracoes::CAMPOS` e o `UsuariosConfiguracoesRequest` passam a aceitar as colunas novas
(alteração em massa da spec 003). Validação (FR-064): mínimos ≤ máximos; percentuais 0–100;
tempos e limites ≥ 0; multiplicador e prêmio máximo > 0; `periodo_jogos` no enum.

### `clientes_configuracoes` (FR-060)

| Coluna nova | Tipo | Padrão |
|---|---|---|
| apostar_jogadores | boolean | `true` |
| periodo_jogos | varchar(20) | `Depois de amanhã` |
| delay_ao_vivo | unsigned smallint | 15 |
| multiplicador | unsigned int | 1000 |
| ganho_multiplo_palpites | decimal(5,2) | 0 |

`cancelar_aposta` continua existindo sem uso (FR-050b).

### `visitantes_configuracoes` (FR-061)

| Coluna nova | Tipo | Padrão |
|---|---|---|
| apostar_jogadores | boolean | `true` |
| periodo_jogos | varchar(20) | `Depois de amanhã` |
| data_travamento_sistema | dateTime null | sem trava |
| quantidade_minima_opcoes / quantidade_maxima_opcoes | unsigned smallint | 1 / 20 |
| valor_minimo_aposta / valor_maximo_aposta | decimal(15,2) | 2.00 / 1000.00 |
| odd_minima | decimal(8,2) | 1.00 |
| premio_maximo | decimal(15,2) | 5000.00 |
| multiplicador | unsigned int | 1000 |
| ganho_multiplo_palpites | decimal(5,2) | 0 |
| horas_validade_codigo | unsigned smallint | 48 |

### `configuracoes` (FR-062)

| Coluna nova | Tipo | Padrão |
|---|---|---|
| nome_sistema | varchar(100) | `WSSports` |
| mensagem_bilhete | varchar(500) | `BOA SORTE!` (bilhetes sem vendedor: cliente e visitante Pendente) |

### `confrontos` e `confrontos_ao_vivo` (FR-063)

| Tabela | Coluna nova | Tipo | Padrão |
|---|---|---|---|
| confrontos | limite_valor_apostado | decimal(15,2) | 50000.00 |
| confrontos_ao_vivo | limite_valor_apostado | decimal(15,2) | 5000.00 |

As cargas do provedor (spec 003) não alteram essa coluna (o upsert não a inclui).

## Enums novos (`app/Enums`)

| Enum | Casos (valor gravado) |
|---|---|
| `SituacaoAposta` | `Pendente`, `EmAnálise` (`Em análise`), `Ativa`, `Recusada`, `Expirada`, `Cancelada` |
| `ResultadoAposta` | `Aguardando`, `Vencedor`, `Perdedor` |
| `TipoAposta` | `PréJogo` (`Pré-jogo`), `AoVivo` (`Ao vivo`) |
| `FormaPagamento` | `Dinheiro`, `Saldo`, `PromoçãoEsportes` (`Promoção esportes`) |
| `AceitarAlteracoes` | `Nenhuma`, `SomenteParaMaior` (`Somente para maior`), `Qualquer` |
| `SituacaoPalpite` | `Ativo`, `Cancelado` |
| `AcaoHistoricoAposta` | `CancelarPalpite` (`Cancelar palpite`), `RestaurarPalpite` (`Restaurar palpite`) |
| `PeriodoJogos` | `Hoje`, `Amanhã`, `DepoisDeAmanhã` (`Depois de amanhã`) |
| `TipoRollover` | `Depósito`, `Bônus` |

## Relações

- `Apostas` 1—N `ApostasPalpites`, 1—N `ApostasHistorico`, 1—N `ApostasRollovers`; N—1 `Usuarios`
  (vendedor), N—1 `Clientes`.
- `Clientes` 1—N `ClientesRollovers`; `ClientesRollovers` 1—N `ApostasRollovers`.
- `ClientesTransacoes` (origem `Aposta`/`Estorno`, `referencia_id` = `apostas.id`, sem FK, como na
  spec 002).
