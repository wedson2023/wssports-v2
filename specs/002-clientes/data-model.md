# Data Model: Clientes (Apostadores)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [plan.md](plan.md)

Todas as tabelas são novas, com `created_at`, `updated_at` e `deleted_at` (constituição), prefixo
`clientes_` nas tabelas ligadas a `clientes` (v1.9.0) e valores de enum em português com a
primeira letra maiúscula e acentos (v1.13.0). Decisões em [research.md](research.md).

## Tabela `clientes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| nome | varchar(150) | obrigatório |
| ddi | varchar(3) | só dígitos, 1 a 3; padrão `55` |
| telefone | varchar(40) | só dígitos; 10–11 se `ddi = 55`, 4–14 nos demais; recebe `_deleted_<timestamp>` na exclusão |
| email | varchar(150) null | minúsculas, formato válido; recebe o sufixo na exclusão |
| password | varchar(255) | hash; nunca retornada (exceção v1.12.0) |
| cpf | varchar(40) null | só dígitos, 11, dígitos verificadores válidos; recebe o sufixo na exclusão |
| data_nascimento | date | 18 anos ou mais na data do cadastro |
| genero | varchar(20) | `Genero`: `'Masculino'`, `'Feminino'`, `'Outro'`, `'Não informado'` |
| codigo_afiliado | varchar(50) null | texto livre, sem FK (R-08) |
| ativo | boolean | padrão `true` |
| saldo | decimal(15,2) | padrão 0; ≥ 0 |
| saldo_promocao_esportes | decimal(15,2) | padrão 0; ≥ 0 |
| saldo_promocao_cassino | decimal(15,2) | padrão 0; ≥ 0 |
| tokens_validos_desde | timestamp null | tokens com `iat` anterior são recusados (R-02) |
| created_at / updated_at / deleted_at | timestamp | |

**Índices**: único (`ddi`, `telefone`); único `cpf`; único `email` (vários `NULL` permitidos);
índices em `nome` e `created_at`.

**Saldos**: só mudam pelo serviço `SaldoClientes` (FR-041); nunca pela edição.

## Tabela `clientes_transacoes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id` | |
| carteira | varchar(30) | `Carteira`: `'Saldo'`, `'Promoção esportes'`, `'Promoção cassino'` |
| tipo | varchar(10) | `TipoTransacao`: `'Crédito'`, `'Débito'` |
| origem | varchar(30) | `OrigemTransacao`: `'Ajuste manual'`, `'Promoção'`, `'Aposta'`, `'Prêmio'`, `'Estorno'` |
| referencia_id | unsigned bigint null | id do registro de origem, sem FK (R-08) |
| valor | decimal(15,2) | > 0 |
| saldo_anterior | decimal(15,2) | |
| saldo_posterior | decimal(15,2) | anterior ± valor; igual ao saldo da carteira logo após |
| usuarios_id | FK → `usuarios.id` null | autor do painel; `null` = sistema |
| observacao | varchar(255) null | obrigatória em `'Ajuste manual'` e `'Estorno'` |
| created_at / updated_at / deleted_at | timestamp | nunca editada nem excluída |

**Índices**: (`clientes_id`, `created_at`); (`clientes_id`, `carteira`); (`origem`,
`referencia_id`) para o estorno de promoção.

## Tabela `clientes_configuracoes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id`, único | um registro por cliente |
| realizar_aposta | boolean | |
| apostar_ao_vivo | boolean | |
| apostar_outros_esportes | boolean | |
| cancelar_aposta | boolean | sem uso desde a spec 004: o cliente online não cancela apostas (FR-050b da spec 004) |
| aceita_promocao | boolean | único campo que o cliente também altera |
| bloquear_saque | boolean | |
| quantidade_minima_opcoes | unsigned smallint | ≥ 1 e ≤ máxima |
| quantidade_maxima_opcoes | unsigned smallint | |
| valor_minimo_aposta | decimal(15,2) | > 0 e ≤ máximo |
| valor_maximo_aposta | decimal(15,2) | |
| premio_maximo | decimal(15,2) | > 0 |
| valor_maximo_diario | decimal(15,2) | > 0 (apostado por dia) |
| valor_maximo_saque_diario | decimal(15,2) | > 0 |
| quantidade_maxima_saques_diaria | unsigned smallint | ≥ 1 |
| odd_minima | decimal(8,2) | ≥ 1,00 e ≤ máxima |
| odd_maxima | decimal(8,2) | |
| esportes_permitidos | json | lista de nomes, pelo menos 1; sem FK (R-08) |
| apostar_jogadores, periodo_jogos, delay_ao_vivo, multiplicador, ganho_multiplo_palpites | | acrescentadas pela spec 004 (ver o data-model de lá) |
| created_at / updated_at / deleted_at | timestamp | |

## Valores padrão das colunas de `clientes_configuracoes`

A tabela `clientes_configuracoes_padrao` foi removida pela spec 003 (2026-10-01): não existe tabela
padrão. Os valores abaixo passaram a ser o padrão das colunas de `clientes_configuracoes` e valem
para todo cliente novo:

| Campo | Valor |
|---|---|
| realizar_aposta / apostar_ao_vivo / apostar_outros_esportes | `true` |
| cancelar_aposta | `false` |
| aceita_promocao / bloquear_saque | `true` / `false` |
| quantidade_minima_opcoes / quantidade_maxima_opcoes | 1 / 20 |
| valor_minimo_aposta / valor_maximo_aposta | 2.00 / 1000.00 |
| premio_maximo | 50000.00 |
| valor_maximo_diario | 5000.00 |
| valor_maximo_saque_diario / quantidade_maxima_saques_diaria | 5000.00 / 5 |
| odd_minima / odd_maxima | 1.90 / 30.00 |
| esportes_permitidos | `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]` |

## Tabela `clientes_meios_pagamento`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id` | |
| tipo | varchar(30) | `TipoMeioPagamento`: `'Pix'`, `'Transferência bancária'` |
| principal | boolean | exatamente um `true` por cliente com meios |
| pix_nome_titular | varchar(150) null | obrigatório se `Pix` |
| pix_tipo_chave | varchar(20) null | `TipoChavePix`: `'CPF'`, `'CNPJ'`, `'E-mail'`, `'Telefone'`, `'Chave aleatória'`; obrigatório se `Pix` |
| pix_chave | varchar(150) null | validada pelo tipo (R-11); obrigatória se `Pix` |
| banco_codigo | varchar(3) null | 3 dígitos; obrigatório se transferência |
| banco_nome | varchar(100) null | obrigatório se transferência |
| agencia | varchar(10) null | só dígitos; obrigatória se transferência |
| conta | varchar(20) null | só dígitos; obrigatória se transferência |
| conta_digito | varchar(2) null | obrigatório se transferência |
| conta_tipo | varchar(20) null | `TipoConta`: `'Corrente'`, `'Poupança'`; obrigatório se transferência |
| titular_nome | varchar(150) null | obrigatório se transferência |
| titular_documento | varchar(14) null | CPF ou CNPJ válido; obrigatório se transferência |
| created_at / updated_at / deleted_at | timestamp | |

**Unicidade (aplicação, entre não excluídos do mesmo cliente)**: `pix_chave` no Pix;
(`banco_codigo`, `agencia`, `conta`, `conta_digito`) na transferência. **Índice**: (`clientes_id`,
`principal`).

## Tabela `clientes_promocoes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| nome | varchar(150) | obrigatório |
| descricao | text null | |
| modalidade | varchar(20) | `ModalidadePromocao`: `'Esportes'`, `'Cassino'` |
| categoria | varchar(30) | `CategoriaPromocao`: `'Primeiro cadastro'`, `'Primeiro depósito'`, `'Qualquer depósito'`, `'Indicação'` |
| tipo_ganho | varchar(20) | `TipoGanho`: `'Fixo'`, `'Percentual'` (Percentual só nas categorias de depósito) |
| valor | decimal(15,2) | Fixo: reais > 0; Percentual: > 0 e ≤ 100 |
| rollover | unsigned smallint | ≥ 0; ≥ 1 em `'Primeiro depósito'` |
| valor_minimo_aposta | decimal(15,2) | > 0 e ≤ máximo |
| valor_maximo_aposta | decimal(15,2) | > 0 |
| valor_maximo_deposito | decimal(15,2) null | > 0; obrigatório se Percentual |
| valor_maximo_conversao | decimal(15,2) | > 0; teto do que vira saldo real após o rollover |
| odd_minima_aposta_simples | decimal(8,2) | ≥ 1,00 |
| odd_minima_aposta_multipla | decimal(8,2) | ≥ 1,00 |
| data_inicio | datetime | |
| data_fim | datetime null | ≥ `data_inicio`; `null` = sem fim |
| ativa | boolean | padrão `true` |
| estorno_situacao | varchar(20) null | `SituacaoEstorno`: `'Em andamento'`, `'Concluído'`; `null` = não estornada |
| estorno_motivo | varchar(255) null | |
| estorno_usuarios_id | FK → `usuarios.id` null | quem estornou |
| estorno_iniciado_em | datetime null | |
| estorno_concluido_em | datetime null | |
| estorno_total_clientes | unsigned int | padrão 0 |
| estorno_clientes_processados | unsigned int | padrão 0 |
| estorno_valor_total | decimal(15,2) | padrão 0 |
| created_at / updated_at / deleted_at | timestamp | |

**Índice**: (`categoria`, `modalidade`, `ativa`).

**Vigente** = `ativa`, não excluída, `estorno_situacao` nula e `data_inicio ≤ agora ≤ data_fim`
(ou `data_fim` nula). **Sobreposição proibida**: duas ativas da mesma categoria e modalidade com
períodos que se cruzam (R-16). **Aplicada** = existe transação `'Promoção'` com `referencia_id` da
promoção (bloqueia alterar valor, tipo de ganho, categoria e modalidade).

## Tabela `clientes_codigos_recuperacao`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id` | |
| codigo | varchar(255) | hash do código de 6 dígitos |
| tentativas | unsigned tinyint | padrão 0; invalidado na 5ª errada |
| expira_em | datetime | criação + 15 minutos |
| usado_em | datetime null | |
| invalidado_em | datetime null | novo pedido ou 5 tentativas |
| created_at / updated_at / deleted_at | timestamp | |

**Código válido** = `usado_em` e `invalidado_em` nulos e `expira_em > agora`.

## Relacionamentos (models)

- `Clientes` hasOne `ClientesConfiguracoes`; hasMany `ClientesTransacoes`,
  `ClientesMeiosPagamento`, `ClientesCodigosRecuperacao`.
- `ClientesTransacoes` belongsTo `Clientes` e `Usuarios` (autor, opcional).
- `ClientesPromocoes` belongsTo `Usuarios` (`estorno_usuarios_id`).

## Enums (`app/Enums`)

Nomes das classes sem acento; casos em `PascalCase` com acento (R-05).

| Enum | Casos |
|---|---|
| `Genero` | Masculino, Feminino, Outro, NãoInformado |
| `Carteira` | Saldo, PromoçãoEsportes, PromoçãoCassino (+ `coluna()`) |
| `TipoTransacao` | Crédito, Débito |
| `OrigemTransacao` | AjusteManual, Promoção, Aposta, Prêmio, Estorno |
| `ModalidadePromocao` | Esportes, Cassino (+ `carteira(): Carteira`) |
| `CategoriaPromocao` | PrimeiroCadastro, PrimeiroDepósito, QualquerDepósito, Indicação (+ `aceita_percentual(): bool`) |
| `TipoGanho` | Fixo, Percentual |
| `SituacaoEstorno` | EmAndamento, Concluído |
| `TipoMeioPagamento` | Pix, TransferênciaBancária |
| `TipoChavePix` | Cpf, Cnpj, Email, Telefone, ChaveAleatória |
| `TipoConta` | Corrente, Poupança |

## Permissões (guard `api`)

Definidas em `App\Enums\Funcao` (`PERMISSOES_CLIENTES`), no mesmo padrão das de usuários.

| Permissão | Funções que podem usar | Padrão |
|---|---|---|
| `clientes.listar` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes.ver_dados_completos` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes.editar` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes.editar_configuracoes` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes.movimentar_saldo` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes_promocoes.gerenciar` | Admin, Supervisor, Gerente | Admin, Supervisor, Gerente |
| `clientes.excluir` | Admin, Supervisor | Admin, Supervisor |
| `clientes.restaurar` | Admin, Supervisor | Admin, Supervisor |
| `clientes_promocoes.estornar` | Admin, Supervisor | Admin, Supervisor |

## Estados

**Cliente**

```text
cadastro ──► ativo ◄──► inativo
               │            │
               └──► excluído ◄┘   (sufixo em telefone/cpf/email, tokens_validos_desde = agora)
                       │
                       └──► restaurado — só sem conflito de dados únicos
```

- Inativo: `403` na área do cliente; excluído: `401` (o guard não encontra o registro).
- Desativar, excluir, trocar ou recuperar senha: `tokens_validos_desde = agora`.

**Promoção**

```text
ativa ◄──► inativa
  │           │
  └──► estorno "Em andamento" ──► estorno "Concluído"   (fica inativa; não edita nem reativa)
```
