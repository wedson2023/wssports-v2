# Data Model: Painel de controle — base do painel e Dashboard

**Feature**: [spec.md](spec.md) | **Research**: [research.md](research.md)

A Dashboard só lê dados existentes (apostas, palpites, confrontos, depósitos, saques, encerramentos,
usuários e configurações do vendedor). Esta spec cria **1 tabela**, **3 colunas** em
`configuracoes`, **4 índices** e **9 permissões**.

## 1. Tabelas lidas (sem alteração de colunas)

| Tabela | Uso |
|---|---|
| `apostas` | `valor`, `premio`, `comissao`, `situacao`, `resultado`, `forma_pagamento`, `usuarios_id`, `clientes_id`, `codigo`, `nome`, `created_at` |
| `apostas_palpites` | `confrontos_id`, `especiais_id`, `especiais_opcoes_id`, `codigo_cotacao`, `confrontos_jogadores_id`, `jogador_tipo`, `situacao`, `resultado` |
| `confrontos` | `situacao`, times e horário |
| `usuarios` | hierarquia (`usuarios_id`), `nome`, inclusive excluídos (R-03) |
| `usuarios_configuracoes` | `limite_geral`, `limite_duplo`, `limite_simples` |
| `depositos` | `situacao = Aprovado`, `pago_em`, `valor` |
| `saques` | `situacao = Pago`, `pago_em`, `valor` |
| `apostas_encerramentos` | `situacao` Registrado/Creditado, `decidido_em`, `valor` |

## 2. Tabela nova `apostas_combinacoes_risco`

Combinações de risco novas registradas pelo agendador (R-11), única origem dos avisos do painel e
do WhatsApp (R-12). Uma linha por combinação; uma chave já registrada hoje ou ontem não é gravada
de novo.

| Coluna | Tipo | Regra / comentário |
|---|---|---|
| `id` | id | |
| `dia` | date | Dia (Brasília) em que a combinação surgiu |
| `chave` | char(40) | `sha1` da assinatura de jogos e opções; única com `dia` |
| `jogos` | json | Nomes dos jogos no momento do aviso (ex.: `["Flamengo x Vasco", …]`) |
| `opcoes` | json | Nomes das opções na mesma ordem dos jogos |
| `codigos` | json | Códigos dos bilhetes da combinação quando surgiu |
| `usuarios_ids` | json | Ids dos vendedores dos bilhetes, para filtrar os avisos do painel pela rede |
| `quantidade_bilhetes` | unsignedInteger | Nº de bilhetes quando surgiu |
| `valor_apostado` | decimal(15,2) | Soma apostada quando surgiu, em reais |
| `premio` | decimal(15,2) | Prêmio somado quando surgiu, em reais |
| `situacao_whatsapp` | string(20) | `Pendente`, `Enviado`, `Falhou` ou `Dispensado` (enum `SituacaoAvisoWhatsapp`) |
| `tentativas_whatsapp` | unsignedTinyInteger | Tentativas de envio (máx. 3), padrão 0 |
| `enviado_em` | timestamp null | Quando a mensagem foi aceita pelo CallMeBot |
| `erro_whatsapp` | string(255) null | Último erro de envio |
| `created_at`, `updated_at`, `deleted_at` | | padrão da Constituição |

**Índices**: `unique(dia, chave)`; `index(situacao_whatsapp, tentativas_whatsapp)`.

**Transições de `situacao_whatsapp`**:

```text
(nova) ──aviso ligado e telefone/chave definidos──▶ Pendente
(nova) ──aviso desligado ou sem telefone/chave────▶ Dispensado
Pendente ──envio aceito──────────────────────────▶ Enviado
Pendente ──falha (tentativas < 3)────────────────▶ Pendente (tentativas + 1)
Pendente ──3ª falha──────────────────────────────▶ Falhou
```

Model `ApostasCombinacoesRisco` com `SoftDeletes`, casts `jogos`/`opcoes`/`codigos`/`usuarios_ids`
→ array, `dia` → date,
valores `decimal:2`, `situacao_whatsapp` → enum.

## 3. Colunas novas em `configuracoes`

| Coluna | Tipo | Padrão | Comentário |
|---|---|---|---|
| `aviso_combinacao_whatsapp` | boolean | false | Liga o aviso de combinação de risco nova no WhatsApp do Admin |
| `telefone_whatsapp_admin` | string(15) null | null | Telefone do WhatsApp do Admin, só dígitos com DDI (ex.: 5582999999999) |
| `chave_callmebot` | text null | null | Chave do CallMeBot do telefone do Admin, criptografada |

Model `Configuracoes`: `fillable` e `casts` (`boolean`, `encrypted`), `chave_callmebot` em `$hidden`.

**Validação** (`ConfiguracoesAvisosWhatsappRequest`, campos `sometimes`):

- `aviso_combinacao_whatsapp`: boolean;
- `telefone_whatsapp_admin`: nulo ou 12 a 15 dígitos;
- `chave_callmebot`: nulo ou texto até 64 caracteres; ausente = mantém a atual;
- ligar o aviso sem telefone ou sem chave (somando o enviado e o já gravado) → 422 "Informe o
  telefone e a chave do CallMeBot para ligar o aviso.".

## 4. Índices novos (migration própria)

| Tabela | Índice | Para |
|---|---|---|
| `apostas` | `(situacao, created_at)` | somas do Admin sem filtro de vendedor e Caixa online |
| `depositos` | `(situacao, pago_em)` | Depósitos online do dia |
| `saques` | `(situacao, pago_em)` | Saques pagos do dia |
| `apostas_encerramentos` | `(situacao, decidido_em)` | Encerramentos do dia |

O índice já existente `apostas (usuarios_id, situacao, created_at)` atende ao filtro por rede.

## 5. Permissões (`Funcao.php`)

`PERMISSOES_DASHBOARD`:

| Permissão | Admin | Supervisor | Gerente | Vendedor |
|---|:-:|:-:|:-:|:-:|
| `dashboard.ver_apurado` | ✔ | ✔ | ✔ | ✔ |
| `dashboard.ver_situacao_jogos` | ✔ | ✔ | ✔ | ✔ |
| `dashboard.ver_apostas` | ✔ | ✔ | ✔ | ✔ |
| `dashboard.ver_combinacao_risco` | ✔ | ✔ | ✔ | ✘ |
| `dashboard.ver_apostas_um_jogo` | ✔ | ✔ | ✔ | ✔ |
| `dashboard.ver_vendedores` | ✔ | ✔ | ✔ | ✘ |
| `dashboard.ver_jogos_mais_apostados` | ✔ | ✔ | ✔ | ✘ |
| `dashboard.ver_opcoes_mais_apostadas` | ✔ | ✔ | ✔ | ✘ |
| `dashboard.ver_caixa_online` | ✔ | ✔ | ✘ | ✘ |

✔ = recebe ao ser cadastrado e pode usar (`permissoes_padrao` + `pode_usar`); ✘ = `pode_usar`
devolve falso mesmo com a permissão. O Admin pode retirar qualquer uma do Supervisor pela API de
permissões existente.

## 6. Dados fake (Princípio IX)

| Método em `DadosFake` | Valor | Substituído por |
|---|---|---|
| `caixa_usuario()` | `['lancamentos' => '0.00', 'retiradas' => '0.00']` | spec de caixa |
| `percentual_comissao_gerente()` | `'0.00'` | spec de caixa |

## 7. Objetos de resposta (não persistidos)

Formatos completos em [contracts/api.md](contracts/api.md). Valores em dinheiro sempre como string
com 2 casas (`"1234.50"`).

- **Apurado**: limites (só Vendedor), entradas, em_aberto, premiadas, perdidas, saidas,
  comissao_vendedor, depositos_online e saques_pagos (Admin/Supervisor), lancamentos, retiradas,
  comissao_gerente (Gerente+), saldo_liquido, provisorios.
- **Combinação**: chave, jogos[], opcoes[], quantidade_bilhetes, codigos[], valor_apostado, premio,
  primeiro_jogo_em.
