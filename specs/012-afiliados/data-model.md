# Data Model: Afiliados

**Feature**: `012-afiliados` | **Date**: 2026-10-17

Todas as tabelas novas têm `id()`, `timestamps()` e `softDeletes()`; toda coluna criada ou
alterada tem `->comment()` em português (constituição). Dinheiro em `decimal(15,2)`, percentual em
`decimal(5,2)`.

## 1. `afiliados` (nova)

| Coluna | Tipo | Regras |
|---|---|---|
| `nome` | varchar(150) | obrigatório |
| `login` | varchar(80) | obrigatório; único entre `afiliados` e `usuarios`, sem diferenciar maiúsculas, contando excluídos; recebe sufixo na exclusão |
| `password` | varchar(255) | hash (`cast hashed`); mínimo 8, letras e números |
| `email` | varchar(150) nulo | único; minúsculas; obrigatório em cadastro novo; sufixo na exclusão |
| `cpf` | varchar(40) nulo | único; só dígitos; `CpfValido`; obrigatório em cadastro novo; sufixo na exclusão |
| `ddi` | varchar(3) | padrão `55` |
| `telefone` | varchar(20) | só dígitos; DDI 55: 10 ou 11 dígitos |
| `cidade` | varchar(100) nulo | |
| `estado` | char(2) nulo | UF ou `EX` (estrangeiro), como no antigo |
| `endereco` | varchar(255) nulo | |
| `codigo` | varchar(80) | único; maiúsculas; código público do link `?user=`; sufixo na exclusão |
| `situacao` | varchar(20) | enum `SituacaoAfiliado`: `Em análise`, `Ativo`, `Inativo`, `Recusado` |
| `motivo_recusa` | varchar(255) nulo | preenchido na recusa |
| `origem_cadastro` | varchar(20) | enum `OrigemCadastroAfiliado`: `Painel`, `Público` |
| `usuarios_id` | FK `usuarios` nula | gerente responsável (função Gerente) |
| `cadastrado_por` | FK `usuarios` nula | usuário que cadastrou pelo painel |
| `decidido_por` | FK `usuarios` nula | quem aprovou ou recusou |
| `decidido_em` | datetime nulo | |
| `tokens_validos_desde` | timestamp nulo | tokens emitidos antes deixam de valer |

Índices: `unique(login)`, `unique(email)`, `unique(cpf)`, `unique(codigo)`, `index(situacao)`,
`index(usuarios_id)`, `index(nome)`.

**Model `Afiliados`**: `Authenticatable` + `JWTSubject`, `SoftDeletes`; `hidden: password`;
`pode_acessar()` (Ativo e fora da lixeira); `pode_receber_clientes()` (igual a `pode_acessar`);
`invalidar_tokens()`; `gerar_codigo()`; relações `gerente`, `comissoes`, `comissao_vigente`,
`lancamentos`, `prestacoes`, `ultima_prestacao`, `meio_pagamento`, `clientes`,
`codigos_recuperacao`.

### Ciclo de vida (`situacao`)

```text
cadastro pelo painel ─────────────► Ativo ◄──────► Inativo
cadastro público ──► Em análise ──┬─► Ativo (aprovar: Admin/Supervisor)
                                  └─► Recusado (recusar: Admin/Supervisor, com motivo opcional)
qualquer situação ──► lixeira (excluir) ──► situação anterior (restaurar)
```

- Só `Ativo` entra e recebe clientes novos. `Ativo`, `Inativo`, `Em análise` e `Recusado` geram
  lançamentos dos clientes já ligados; na lixeira, não.
- Inativar, excluir, trocar ou redefinir a senha → `invalidar_tokens()`.
- Recusado pode ser aprovado depois (mesma rota de aprovação).

## 2. `afiliados_comissoes` (nova)

| Coluna | Tipo | Regras |
|---|---|---|
| `afiliados_id` | FK `afiliados` | |
| `tipo_ganho` | varchar(20) | enum `TipoGanhoAfiliado`: `Saldo líquido`, `Qualquer depósito`, `Primeiro depósito` |
| `percentual` | decimal(5,2) | 0 a 100 |
| `vigente_desde` | datetime | |
| `vigente_ate` | datetime nulo | nulo = vigente |
| `usuarios_id` | FK `usuarios` nula | quem definiu (nulo no cadastro público) |

Índice: `(afiliados_id, vigente_ate)`. Só uma linha com `vigente_ate` nulo por afiliado
(garantido pelo serviço, com bloqueio do afiliado).

## 3. `afiliados_lancamentos` (nova)

| Coluna | Tipo | Regras |
|---|---|---|
| `afiliados_id` | FK `afiliados` | |
| `clientes_id` | FK `clientes` | |
| `evento` | varchar(30) | enum `EventoLancamentoAfiliado`: `Depósito`, `Saque`, `Estorno de depósito` |
| `depositos_id` | FK `depositos` nula | depósito e estorno |
| `saques_id` | FK `saques` nula | saque |
| `chave_evento` | varchar(80) | única: `deposito:{id}`, `saque:{id}`, `estorno:{deposito_id}:{valor_estornado_total}` |
| `tipo_ganho` | varchar(20) | regra usada |
| `base` | decimal(15,2) | valor do depósito, do saque ou estornado agora (positivo) |
| `percentual` | decimal(5,2) | percentual usado |
| `valor` | decimal(15,2) | comissão com sinal (+ depósito; − saque e estorno) |
| `ocorrido_em` | datetime | `pago_em` do depósito/saque ou momento do estorno |
| `afiliados_prestacoes_contas_id` | FK nula | prestação que fechou; nulo = em aberto |

Índices: `unique(chave_evento)`, `(afiliados_id, afiliados_prestacoes_contas_id)`,
`(afiliados_id, ocorrido_em)`, `(depositos_id)`.

**Regras de geração** (R-06):

| Tipo de ganho | Depósito aprovado | Saque pago | Estorno do depósito |
|---|---|---|---|
| Qualquer depósito | `+ p × valor` | — | `− p_original × estornado` se o depósito gerou lançamento |
| Primeiro depósito | `+ p × valor` só no primeiro da vida do cliente | — | idem |
| Saldo líquido | `+ p × valor` | `− p × valor` | idem |

Arredondamento: 2 casas, meio para cima (`EscolhaPromocoes::percentual`). Lançamentos nunca são
editados nem excluídos.

## 4. `afiliados_prestacoes_contas` (nova)

| Coluna | Tipo | Regras |
|---|---|---|
| `afiliados_id` | FK `afiliados` | |
| `valor_apurado` | decimal(15,2) | soma dos lançamentos fechados (pode ser negativo) |
| `valor_pago` | decimal(15,2) | apurado se > 0; senão 0 |
| `quantidade_lancamentos` | unsigned int | |
| `periodo_inicio` | datetime | fim da prestação anterior ou `created_at` do afiliado |
| `periodo_fim` | datetime | momento da prestação |
| `usuarios_id` | FK `usuarios` | quem prestou |
| `observacao` | varchar(500) nulo | |

Índice: `(afiliados_id, periodo_fim)`. Nunca editada nem excluída.

## 5. `afiliados_meios_pagamento` (nova)

| Coluna | Tipo | Regras |
|---|---|---|
| `afiliados_id` | FK `afiliados`, única | um registro por afiliado |
| `pix_tipo_chave` | varchar(20) | enum `TipoChavePix` existente (`CPF`, `CNPJ`, `E-mail`, `Telefone`, `Chave aleatória`) |
| `pix_chave` | varchar(150) | validada e normalizada conforme o tipo (R-13) |

## 6. `afiliados_codigos_recuperacao` (nova)

Igual a `clientes_codigos_recuperacao`: `afiliados_id` (FK), `codigo` (hash), `tentativas`
(tinyint, padrão 0), `expira_em`, `usado_em` nulo, `invalidado_em` nulo.

## 7. Alterações em tabelas existentes

| Tabela | Alteração |
|---|---|
| `clientes` | + `afiliados_id` FK nula (`index`), preenchida por `UPPER(TRIM(codigo_afiliado)) = afiliados.codigo`; − `codigo_afiliado` |
| `clientes_promocoes` | + `afiliados_id` FK nula, preenchida do mesmo jeito; − `codigo_afiliado` |
| `configuracoes` | + `afiliados_tipo_ganho_padrao` varchar(20) padrão `Saldo líquido`; + `afiliados_percentual_padrao` decimal(5,2) padrão 0 |

O `down()` das migrations recria `codigo_afiliado` a partir do `codigo` do afiliado ligado.

## 8. Enums novos (`app/Enums`)

- `SituacaoAfiliado`: `EmAnálise = 'Em análise'`, `Ativo`, `Inativo`, `Recusado`.
- `OrigemCadastroAfiliado`: `Painel`, `Público`.
- `TipoGanhoAfiliado`: `SaldoLíquido = 'Saldo líquido'`, `QualquerDepósito = 'Qualquer depósito'`,
  `PrimeiroDepósito = 'Primeiro depósito'`.
- `EventoLancamentoAfiliado`: `Depósito`, `Saque`, `EstornoDepósito = 'Estorno de depósito'`.

## 9. Permissões (`Funcao::PERMISSOES_AFILIADOS`)

| Permissão | Admin | Supervisor | Gerente | Vendedor |
|---|---|---|---|---|
| `afiliados.listar` | ✔ | ✔ | ✔ (só os seus) | — |
| `afiliados.consultar` | ✔ | ✔ | ✔ | — |
| `afiliados.cadastrar` | ✔ | ✔ | ✔ | — |
| `afiliados.editar` | ✔ | ✔ | ✔ | — |
| `afiliados.alterar_situacao` | ✔ | ✔ | ✔ ativar/inativar (aprovar/recusar não) | — |
| `afiliados.prestar_contas` | ✔ | ✔ | ✔ | — |
| `afiliados.editar_comissao` | ✔ | ✔ | pode usar se receber (não vem por padrão) | — |
| `afiliados.excluir` | ✔ | ✔ | — | — |
| `afiliados.restaurar` | ✔ | ✔ | — | — |

## 10. Estado no navegador

- Sessão (`utils/session.js`, chave existente): `tipo` ganha o valor `'afiliado'`
  (`{ tipo: 'afiliado', token, nome, vence_em }`). Nenhuma chave nova no `localStorage`.
