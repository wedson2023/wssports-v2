# Data Model: Clientes (Apostadores)

**Feature**: `002-clientes` | **Data**: 2026-09-29 | **Plano**: [plan.md](plan.md)

Todas as tabelas são novas, com `created_at`, `updated_at` e `deleted_at` (constituição) e prefixo
`clientes_` nas tabelas ligadas a `clientes` (v1.9.0). Decisões em [research.md](research.md).

## Tabela `clientes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| nome | varchar(150) | obrigatório |
| codigo_pais | varchar(3) | só dígitos, 1 a 3; padrão `55` |
| telefone | varchar(40) | só dígitos; 10–11 se `codigo_pais = 55`, 4–14 nos demais; recebe o sufixo `_deleted_<timestamp>` na exclusão |
| cpf | varchar(40) | só dígitos, 11, dígitos verificadores válidos; recebe o sufixo na exclusão |
| password | varchar(255) | hash (`cast hashed`); nunca retornada |
| data_nascimento | date | 18 anos ou mais na data do cadastro |
| genero | varchar(20) | enum `Genero` |
| codigo_afiliado | varchar(50) null | texto livre, sem FK (R-08) |
| aceita_promocao | boolean | padrão `true` |
| ativo | boolean | padrão `true` |
| saldo | decimal(15,2) | padrão 0; ≥ 0 |
| saldo_promocao_esportes | decimal(15,2) | padrão 0; ≥ 0 |
| saldo_promocao_cassino | decimal(15,2) | padrão 0; ≥ 0 |
| tokens_validos_desde | timestamp null | tokens emitidos antes desta data são recusados (R-02) |
| created_at / updated_at / deleted_at | timestamp | |

**Índices**: único (`codigo_pais`, `telefone`); único `cpf`; índice `nome`; índice `created_at`.

**Saldos**: só mudam pelo serviço `SaldoClientes` (FR-033); nunca pela edição.

## Tabela `clientes_transacoes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id` | obrigatório |
| carteira | varchar(30) | enum `Carteira`: `saldo`, `saldo_promocao_esportes`, `saldo_promocao_cassino` |
| tipo | varchar(10) | enum `TipoTransacao`: `credito`, `debito` |
| origem | varchar(30) | enum `OrigemTransacao`: `ajuste_manual`, `promocao`, `aposta`, `premio`, `estorno` |
| referencia_id | unsigned bigint null | id do registro de origem, sem FK (R-08) |
| valor | decimal(15,2) | > 0, até 2 casas |
| saldo_anterior | decimal(15,2) | valor da carteira antes |
| saldo_posterior | decimal(15,2) | anterior ± valor; igual ao saldo da carteira logo após |
| usuarios_id | FK → `usuarios.id` null | autor do painel; `null` = sistema |
| observacao | varchar(255) null | obrigatória na origem `ajuste_manual` |
| created_at / updated_at / deleted_at | timestamp | nunca editada nem excluída (FR-039) |

**Índices**: (`clientes_id`, `created_at`) para o extrato; (`clientes_id`, `carteira`).

## Tabela `clientes_configuracoes` (configurações do cliente)

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id`, único | um registro por cliente |
| realizar_aposta | boolean | |
| apostar_ao_vivo | boolean | |
| apostar_outros_esportes | boolean | |
| cancelar_aposta | boolean | |
| quantidade_minima_opcoes | unsigned smallint | ≥ 1 e ≤ máxima |
| quantidade_maxima_opcoes | unsigned smallint | ≥ mínima |
| valor_minimo_aposta | decimal(15,2) | > 0 e ≤ máximo |
| valor_maximo_aposta | decimal(15,2) | ≥ mínimo |
| premio_maximo | decimal(15,2) | > 0 |
| valor_maximo_diario | decimal(15,2) | > 0 |
| odd_minima | decimal(8,2) | ≥ 1,00 e ≤ máxima |
| odd_maxima | decimal(8,2) | ≥ mínima |
| esportes_permitidos | json | lista de nomes (texto), pelo menos 1; sem FK (R-08) |
| created_at / updated_at / deleted_at | timestamp | |

## Tabela `clientes_configuracoes_padrao` (registro único)

Mesmas colunas de `clientes_configuracoes`, sem `clientes_id`. Sempre existe um único registro
(id 1), criado pelo seeder com os valores da antiga `travas_gerentes`:

| Campo | Valor |
|---|---|
| realizar_aposta / apostar_ao_vivo / apostar_outros_esportes | `true` |
| cancelar_aposta | `false` |
| quantidade_minima_opcoes / quantidade_maxima_opcoes | 1 / 20 |
| valor_minimo_aposta / valor_maximo_aposta | 2.00 / 1000.00 |
| premio_maximo | 50000.00 |
| valor_maximo_diario | 5000.00 |
| odd_minima / odd_maxima | 1.90 / 30.00 |
| esportes_permitidos | `["FUTEBOL", "HOQUEI NO GELO", "BAISEBOL"]` |

As validações de coerência são as mesmas de `clientes_configuracoes` (FR-044).

## Tabela `clientes_promocoes`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| nome | varchar(150) | obrigatório |
| descricao | text null | |
| modalidade | varchar(20) | enum `ModalidadePromocao`: `esportes`, `cassino` |
| categoria | varchar(30) | enum `CategoriaPromocao`: `primeiro_cadastro`, `primeiro_deposito`, `qualquer_deposito`, `indicacao` |
| valor | decimal(15,2) | > 0 |
| data_inicio | datetime | obrigatório |
| data_fim | datetime null | ≥ `data_inicio`; `null` = sem fim |
| ativa | boolean | padrão `true` |
| created_at / updated_at / deleted_at | timestamp | |

**Vigente** = `ativa`, não excluída e `data_inicio ≤ agora ≤ data_fim` (ou `data_fim` nula).
**Sobreposição proibida**: duas promoções ativas da mesma categoria e mesma modalidade com
períodos que se cruzam; categorias diferentes podem se sobrepor (R-15).

## Tabela `clientes_codigos_recuperacao`

| Coluna | Tipo | Regras |
|---|---|---|
| id | bigint PK | |
| clientes_id | FK → `clientes.id` | |
| codigo | varchar(255) | hash do código de 6 dígitos |
| tentativas | unsigned tinyint | padrão 0; na 5ª errada o código é invalidado |
| expira_em | datetime | criação + 15 minutos |
| usado_em | datetime null | preenchido na troca de senha |
| invalidado_em | datetime null | novo pedido ou 5 tentativas erradas |
| created_at / updated_at / deleted_at | timestamp | |

**Código válido** = `usado_em` e `invalidado_em` nulos e `expira_em > agora`.

## Relacionamentos (models)

- `Clientes` hasOne `ClientesConfiguracoes`; hasMany `ClientesTransacoes`; hasMany
  `ClientesCodigosRecuperacao`.
- `ClientesTransacoes` belongsTo `Clientes`; belongsTo `Usuarios` (autor, opcional).
- `ClientesConfiguracoesPadrao`: método estático `atual()` que devolve o registro único.

## Enums (`app/Enums`)

| Enum | Valores |
|---|---|
| `Genero` | masculino, feminino, outro, nao_informado |
| `Carteira` | saldo, saldo_promocao_esportes, saldo_promocao_cassino |
| `TipoTransacao` | credito, debito |
| `OrigemTransacao` | ajuste_manual, promocao, aposta, premio, estorno |
| `ModalidadePromocao` | esportes, cassino (método `carteira()` → `Carteira`) |
| `CategoriaPromocao` | primeiro_cadastro, primeiro_deposito, qualquer_deposito, indicacao |
| `PermissaoCliente` | as 9 permissões; método `funcoes_permitidas()` (R-03) |

## Permissões (guard `api`)

| Permissão | Funções que podem usar | Padrão |
|---|---|---|
| `clientes.listar` | Admin, Supervisor, Gerente | Admin |
| `clientes.ver_dados_completos` | Admin, Supervisor, Gerente | Admin |
| `clientes.editar` | Admin, Supervisor, Gerente | Admin |
| `clientes.editar_configuracoes` | Admin, Supervisor, Gerente | Admin |
| `clientes.movimentar_saldo` | Admin, Supervisor, Gerente | Admin |
| `clientes_promocoes.gerenciar` | Admin, Supervisor, Gerente | Admin |
| `clientes.excluir` | Admin, Supervisor | Admin |
| `clientes.restaurar` | Admin, Supervisor | Admin |
| `clientes.editar_configuracoes_padrao` | Admin, Supervisor | Admin |

## Estados do cliente

```text
cadastro ──► ativo ◄──► inativo
               │            │
               └──► excluído ◄┘   (sufixo em telefone/cpf, tokens_validos_desde = agora)
                       │
                       └──► restaurado (ativo ou inativo, como estava) — só sem conflito de dados únicos
```

- Inativo ou excluído: `pode_acessar()` falso; login, renovação, recuperação e rotas da área do
  cliente recusadas — inativo com `403`, excluído com `401` (o guard não encontra o registro).
- Desativar, excluir, trocar senha ou recuperar senha: `tokens_validos_desde = agora`.
